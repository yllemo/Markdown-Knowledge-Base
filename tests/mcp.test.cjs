// Run: PHP_BINARY=/path/to/php node tests/mcp.test.cjs (PowerShell: $env:PHP_BINARY=...)
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const crypto = require('node:crypto');
const net = require('node:net');
const assert = require('node:assert/strict');

(async () => {
    const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'mdkb-mcp-'));
    const root = path.resolve(__dirname, '..');
    for (const dir of ['api', 'classes', 'config', 'mcp']) fs.mkdirSync(path.join(fixture, dir));
    for (const file of ['api/settings.php', 'classes/McpServer.php', 'classes/FileManager.php', 'classes/SearchEngine.php', 'config/config.php', 'mcp/index.php']) fs.copyFileSync(path.join(root, file), path.join(fixture, file));
    fs.writeFileSync(path.join(fixture, 'config/config.custom.php'), "<?php return ['password'=>'test-secret','current_knowledgebase'=>'2026'];");
    fs.mkdirSync(path.join(fixture, 'content/2026'), { recursive: true });
    fs.writeFileSync(path.join(fixture, 'content/2026/meeting.md'), '---\ntags: [planering, team]\n---\n# Agenda\nBudget för projektet.');
    fs.writeFileSync(path.join(fixture, 'content/outside.md'), '# Outside\nBudget secret');
    const listener = net.createServer();
    await new Promise(resolve => listener.listen(0, '127.0.0.1', resolve));
    const port = listener.address().port;
    await new Promise(resolve => listener.close(resolve));
    const server = spawn(process.env.PHP_BINARY || 'php', ['-n', '-S', `127.0.0.1:${port}`, '-t', fixture], { windowsHide: true, stdio: 'ignore' });
    const base = `http://127.0.0.1:${port}`;
    try {
        for (let n = 0; ; n++) {
            try { await fetch(base + '/mcp/'); break; }
            catch (e) { if (n === 40) throw e; await new Promise(resolve => setTimeout(resolve, 100)); }
        }
        const time = Math.floor(Date.now() / 1000).toString();
        const cookie = `kb_auth=${crypto.createHash('sha256').update('test-secret' + time).digest('hex')}; kb_auth_time=${time}`;
        const settings = await (await fetch(base + '/api/settings.php', { headers: { cookie } })).json();
        assert.equal(settings.mcp_key_active, false);
        async function keyAction(action, token = settings.mcp_csrf, loggedIn = true) {
            return fetch(base + '/api/settings.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-MCP-CSRF': token, ...(loggedIn ? { cookie } : {}) }, body: JSON.stringify({ action }) });
        }
        assert.equal((await keyAction('generate_mcp_key', '')).status, 403);
        assert.equal((await keyAction('generate_mcp_key', settings.mcp_csrf, false)).status, 401);
        const first = await (await keyAction('generate_mcp_key')).json();
        assert.match(first.key, /^mdkb_[a-f0-9]{64}$/);
        assert(!fs.readFileSync(path.join(fixture, 'config/config.custom.php'), 'utf8').includes(first.key));
        async function rpc(method, args = {}, key = first.key, extraHeaders = {}, metaVersion = '2026-07-28') {
            return fetch(base + '/mcp/', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json, text/event-stream', Authorization: `Bearer ${key}`, 'MCP-Protocol-Version': metaVersion, 'Mcp-Method': method, ...(args.name ? { 'Mcp-Name': args.name } : {}), ...extraHeaders }, body: JSON.stringify({ jsonrpc: '2.0', id: 1, method, params: { ...args, _meta: { 'io.modelcontextprotocol/protocolVersion': metaVersion, 'io.modelcontextprotocol/clientCapabilities': {} } } }) });
        }
        assert.equal((await fetch(base + '/mcp/', { headers: { cookie } })).status, 401);
        assert.equal((await rpc('server/discover', {}, 'bad')).status, 401);
        assert.equal((await rpc('server/discover', {}, first.key, { Origin: 'https://evil.example' })).status, 403);
        assert.equal((await rpc('server/discover', {}, first.key, { 'Mcp-Method': 'ping' })).status, 400);
        const unsupported = await (await rpc('server/discover', {}, first.key, {}, '2025-11-25')).json();
        assert.equal(unsupported.error.code, -32022);
        const discovery = await (await rpc('server/discover')).json();
        assert.deepEqual(discovery.result.supportedVersions, ['2026-07-28']);
        assert.equal(discovery.result.resultType, 'complete');
        assert.equal((await (await rpc('tools/list')).json()).result.tools.length, 4);
        async function call(name, args) { return (await (await rpc('tools/call', { name, arguments: args })).json()).result; }
        const search = await call('search', { query: 'Budget', tags: ['planering'] });
        assert.equal(search.structuredContent.total, 1);
        assert.equal(search.structuredContent.items[0].path, '2026/meeting.md');
        assert.equal((await call('search', { tags: ['missing'] })).structuredContent.total, 0);
        assert.equal((await call('search', { query: 'tag:team' })).structuredContent.total, 1);
        assert.equal((await call('list_tags', {})).structuredContent.items.length, 2);
        assert.equal((await call('list_documents', { limit: 1 })).structuredContent.total, 1);
        assert.match((await call('read_document', { path: '2026/meeting.md' })).structuredContent.content, /Budget/);
        for (const file of ['outside.md', '../config/config.php', '2026/../outside.md']) assert.equal((await call('read_document', { path: file })).isError, true);
        assert.equal((await rpc('tools/call', { name: 'search', arguments: { limit: -1 } })).status, 400);
        const second = await (await keyAction('generate_mcp_key')).json();
        assert.equal((await rpc('ping')).status, 401);
        assert.equal((await rpc('ping', {}, second.key)).status, 200);
        const publicSettings = await (await fetch(base + '/api/settings.php', { headers: { cookie } })).text();
        assert(!publicSettings.includes(second.key));
        await keyAction('revoke_mcp_key');
        assert.equal((await rpc('ping', {}, second.key)).status, 401);
        console.log('MCP integration tests passed: authentication, CSRF, rotation, revocation, protocol, search, tags and root isolation.');
    } finally {
        server.kill();
        await new Promise(resolve => server.once('exit', resolve));
        fs.rmSync(fixture, { recursive: true, force: true });
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
