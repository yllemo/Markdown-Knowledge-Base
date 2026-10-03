# MCP för Markdown Knowledge Base

Serveradress: `https://din-domän/sökväg/mcp/` (alternativt `mcp/index.php`).
PHP-servern behöver inga nya paket eller bakgrundsprocesser.

1. Logga in och öppna Settings → MCP.
2. Välj **Generera ny nyckel** och kopiera nyckeln. Den visas bara denna gång.
3. Anslut en klient med Streamable HTTP och ange HTTP-headern
   `Authorization: Bearer DIN_NYCKEL`. Använd HTTPS.

En ny nyckel ersätter omedelbart den tidigare. **Återkalla nyckel** stänger
åtkomsten. Nyckelhanteringen sparas direkt. Servern lagrar bara SHA-256-hashen
av en slumpad 256-bitarsnyckel. Vanlig webbinloggning ger ingen MCP-åtkomst.
Skydda `config/config.custom.php` och säkerhetskopior som övrig konfiguration.

## Protokoll och klientstöd

Implementerar publicerad [MCP 2026-07-28](https://modelcontextprotocol.io/specification/2026-07-28).
Klienten måste stödja denna version: `server/discover`, metadata per anrop och
obligatoriska HTTP-headers. Äldre klienter som bara använder `initialize`
stöds inte. Servern använder stateless JSON-svar via Streamable HTTP.
Ingen OAuth-inloggning erbjuds; klienten behöver stöd för egen Bearer-nyckel.
Browser-Origin nekas; anslut från en klient/server som skickar anrop utan Origin.

Exempel med nyckeln i miljövariabeln `MDKB_MCP_KEY`:

```sh
curl https://din-domän/mcp/ \
  -H "Authorization: Bearer $MDKB_MCP_KEY" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2026-07-28' \
  -H 'Mcp-Method: tools/call' \
  -H 'Mcp-Name: search' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"search","arguments":{"query":"budget","tags":["möte"]},"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}'
```

## Verktyg

- `search`: innehåll, filnamn, beskrivning och taggar. `query` stöder `tag:namn`,
  `title:namn`, `"exakt fras"` och `-undantag`. Separata `tags` kräver alla angivna
  taggar. Utelämnad query möjliggör sökning enbart på tags.
- `read_document`: läser exakt `path` från sökningen/listningen (högst 2 MiB).
- `list_documents`: listar dokument.
- `list_tags`: listar frontmatter-taggar och antal dokument per tagg.

Listor använder `limit` (1–100, standard 20), `offset` och returnerar
`next_offset`. Endast Markdown-filer i **sparad, vald innehållsroot** exponeras.
Byte av root ändrar även vad den aktiva nyckeln kan läsa. Inga skrivverktyg finns.
Dokumentinnehåll ska behandlas som data, inte som instruktioner.

Apache-konfigurationen vidarebefordrar Authorization till PHP. För andra
webbservrar/proxyer måste samma header vidarebefordras. Testa efter driftsättning
att både saknad/återkallad nyckel nekas och en giltig nyckel fungerar.

## Test

Kör `node tests/mcp.test.cjs` med PHP i PATH, eller ange sökvägen i
miljövariabeln `PHP_BINARY`. Testerna använder en separat tillfällig datamapp.
