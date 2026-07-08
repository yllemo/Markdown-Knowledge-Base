/**
 * Monaco-based markdown editor for the main Knowledge Base interface.
 *
 * Exposes window.KBMonaco.create(hostEl, options) which returns an adapter
 * object that mimics the small <textarea> surface app.js relies on
 * (value, selectionStart/End, setSelectionRange, focus, scrollTop/Height,
 * addEventListener for 'input'/'scroll') while running a full Monaco editor
 * underneath with markdown-aware features:
 *   - Smart list / checkbox / numbered-list continuation on Enter
 *   - IntelliSense completions (markdown snippets + mermaid templates)
 *   - Fenced code + ```mermaid / ```drawio / ```bpmnio snippets
 *   - Fullscreen toggle (F11) via an app-provided callback
 */
(function () {
    'use strict';

    var MONACO_VERSION = '0.52.2';
    var MONACO_BASE = 'https://cdn.jsdelivr.net/npm/monaco-editor@' + MONACO_VERSION + '/min/vs';

    var monacoLoading = null;
    var providersRegistered = false;

    function loadMonaco() {
        if (window.monaco && window.monaco.editor) {
            return Promise.resolve(window.monaco);
        }
        if (monacoLoading) {
            return monacoLoading;
        }
        monacoLoading = new Promise(function (resolve, reject) {
            if (typeof window.require === 'undefined') {
                reject(new Error('Monaco AMD loader (loader.js) is not available'));
                return;
            }
            try {
                window.require.config({ paths: { vs: MONACO_BASE } });
                window.require(['vs/editor/editor.main'], function () {
                    resolve(window.monaco);
                });
            } catch (err) {
                reject(err);
            }
        });
        return monacoLoading;
    }

    function defineTheme(monaco) {
        try {
            monaco.editor.defineTheme('kb-dark', {
                base: 'vs-dark',
                inherit: true,
                rules: [],
                colors: {
                    'editor.background': '#0d1117',
                    'editor.foreground': '#e6edf3',
                    'editorLineNumber.foreground': '#484f58',
                    'editorLineNumber.activeForeground': '#8b949e',
                    'editor.selectionBackground': '#264f78',
                    'editor.lineHighlightBackground': '#161b22',
                    'editorCursor.foreground': '#58a6ff',
                    'editorIndentGuide.background': '#21262d'
                }
            });
        } catch (e) { /* ignore */ }
    }

    // ---- Markdown-aware completions -------------------------------------

    function buildCompletions(monaco, range) {
        var K = monaco.languages.CompletionItemKind;
        var snippet = monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet;

        function item(label, detail, insertText, kind, asSnippet) {
            return {
                label: label,
                kind: kind || K.Snippet,
                detail: detail,
                insertText: insertText,
                insertTextRules: asSnippet ? snippet : undefined,
                range: range
            };
        }

        return [
            item('mermaid', 'Mermaid flowchart block',
                ['```mermaid', 'graph TD', '    ${1:A}[${2:Start}] --> ${3:B}[${4:End}]', '```', '$0'].join('\n'),
                K.Snippet, true),
            item('mermaid-flowchart', 'Mermaid flowchart (LR)',
                ['```mermaid', 'flowchart LR', '    ${1:A}[${2:Start}] --> ${3:B}{${4:Decision}}', '    ${3:B} -->|${5:Yes}| ${6:C}[${7:Done}]', '    ${3:B} -->|${8:No}| ${1:A}', '```', '$0'].join('\n'),
                K.Snippet, true),
            item('mermaid-sequence', 'Mermaid sequence diagram',
                ['```mermaid', 'sequenceDiagram', '    participant ${1:Alice}', '    participant ${2:Bob}', '    ${1:Alice}->>${2:Bob}: ${3:Hello}', '    ${2:Bob}-->>${1:Alice}: ${4:Hi}', '```', '$0'].join('\n'),
                K.Snippet, true),
            item('mermaid-class', 'Mermaid class diagram',
                ['```mermaid', 'classDiagram', '    class ${1:Animal} {', '        +String ${2:name}', '        +${3:move}()', '    }', '```', '$0'].join('\n'),
                K.Snippet, true),
            item('mermaid-state', 'Mermaid state diagram',
                ['```mermaid', 'stateDiagram-v2', '    [*] --> ${1:Idle}', '    ${1:Idle} --> ${2:Running}', '    ${2:Running} --> [*]', '```', '$0'].join('\n'),
                K.Snippet, true),
            item('mermaid-gantt', 'Mermaid Gantt chart',
                ['```mermaid', 'gantt', '    title ${1:Project}', '    dateFormat YYYY-MM-DD', '    section ${2:Phase 1}', '    ${3:Task} :a1, ${4:2024-01-01}, ${5:7d}', '```', '$0'].join('\n'),
                K.Snippet, true),
            item('mermaid-pie', 'Mermaid pie chart',
                ['```mermaid', 'pie title ${1:Distribution}', '    "${2:A}" : ${3:40}', '    "${4:B}" : ${5:60}', '```', '$0'].join('\n'),
                K.Snippet, true),
            item('drawio', 'draw.io diagram block',
                ['```drawio', '<mxGraphModel dx="800" dy="600" grid="1" gridSize="10" page="1" pageWidth="827" pageHeight="1169">', '  <root>', '    <mxCell id="0"/>', '    <mxCell id="1" parent="0"/>', '  </root>', '</mxGraphModel>', '```', '$0'].join('\n'),
                K.Snippet, true),
            item('bpmnio', 'BPMN 2.0 diagram block',
                ['```bpmnio', '<?xml version="1.0" encoding="UTF-8"?>', '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">', '  <bpmn:process id="Process_1" isExecutable="false">', '    <bpmn:startEvent id="StartEvent_1"/>', '  </bpmn:process>', '</bpmn:definitions>', '```', '$0'].join('\n'),
                K.Snippet, true),
            item('code', 'Fenced code block',
                ['```${1:language}', '${2:code}', '```', '$0'].join('\n'), K.Snippet, true),
            item('table', 'Markdown table',
                ['| ${1:Column A} | ${2:Column B} |', '| --- | --- |', '| ${3:cell} | ${4:cell} |', '$0'].join('\n'),
                K.Snippet, true),
            item('todo', 'Task list item', '- [ ] ${1:task}\n$0', K.Snippet, true),
            item('done', 'Completed task item', '- [x] ${1:task}\n$0', K.Snippet, true),
            item('link', 'Link', '[${1:text}](${2:https://})$0', K.Snippet, true),
            item('image', 'Image', '![${1:alt}](${2:path})$0', K.Snippet, true),
            item('bold', 'Bold text', '**${1:text}**$0', K.Snippet, true),
            item('italic', 'Italic text', '*${1:text}*$0', K.Snippet, true),
            item('h1', 'Heading 1', '# ${1:Title}\n$0', K.Snippet, true),
            item('h2', 'Heading 2', '## ${1:Title}\n$0', K.Snippet, true),
            item('h3', 'Heading 3', '### ${1:Title}\n$0', K.Snippet, true),
            item('quote', 'Blockquote', '> ${1:quote}\n$0', K.Snippet, true),
            item('hr', 'Horizontal rule', '\n---\n$0', K.Snippet, true),
            item('frontmatter', 'YAML front matter',
                ['---', 'title: ${1:Title}', 'tags: ${2:tag1, tag2}', '---', '$0'].join('\n'),
                K.Snippet, true)
        ];
    }

    function registerProviders(monaco) {
        if (providersRegistered) return;
        providersRegistered = true;

        monaco.languages.registerCompletionItemProvider('markdown', {
            triggerCharacters: ['`', '#', '!', '['],
            provideCompletionItems: function (model, position) {
                var word = model.getWordUntilPosition(position);
                var range = {
                    startLineNumber: position.lineNumber,
                    endLineNumber: position.lineNumber,
                    startColumn: word.startColumn,
                    endColumn: word.endColumn
                };
                return { suggestions: buildCompletions(monaco, range) };
            }
        });
    }

    // ---- Smart list continuation ----------------------------------------

    var BULLET_RE = /^(\s*)([-*+])(\s+)(\[[ xX]\]\s+)?/;
    var NUMBERED_RE = /^(\s*)(\d+)([.)])(\s+)/;

    function isSuggestVisible(editor) {
        try {
            var svc = editor._contextKeyService;
            if (svc && typeof svc.getContextKeyValue === 'function') {
                return svc.getContextKeyValue('suggestWidgetVisible') === true;
            }
        } catch (e) { /* ignore */ }
        return false;
    }

    function handleListEnter(monaco, editor, e) {
        var model = editor.getModel();
        var sel = editor.getSelection();
        if (!model || !sel || !sel.isEmpty()) return false;

        var pos = sel.getPosition();
        var line = model.getLineContent(pos.lineNumber);
        var beforeCursor = line.substring(0, pos.column - 1);

        var bullet = beforeCursor.match(BULLET_RE);
        var numbered = beforeCursor.match(NUMBERED_RE);
        if (!bullet && !numbered) return false;

        var match = bullet || numbered;
        var markerLen = match[0].length;
        var rest = line.substring(markerLen);

        // Empty list item -> end the list (remove marker, no new bullet).
        if (rest.trim() === '' && pos.column - 1 <= markerLen) {
            editor.executeEdits('kb-list-end', [{
                range: new monaco.Range(pos.lineNumber, 1, pos.lineNumber, markerLen + 1),
                text: ''
            }]);
            editor.setPosition({ lineNumber: pos.lineNumber, column: 1 });
            return true;
        }

        var indent = match[1];
        var newMarker;
        if (bullet) {
            var checkbox = bullet[4] ? '[ ] ' : '';
            newMarker = indent + bullet[2] + ' ' + checkbox;
        } else {
            var next = parseInt(numbered[2], 10) + 1;
            newMarker = indent + next + numbered[3] + ' ';
        }

        var insertText = '\n' + newMarker;
        editor.executeEdits('kb-list-continue', [{
            range: new monaco.Range(pos.lineNumber, pos.column, pos.lineNumber, pos.column),
            text: insertText
        }]);
        editor.setPosition({ lineNumber: pos.lineNumber + 1, column: newMarker.length + 1 });
        editor.revealPositionInCenterIfOutsideViewport(editor.getPosition());
        return true;
    }

    // ---- Adapter --------------------------------------------------------

    function createAdapter(host, options) {
        var opts = options || {};
        var editor = null;
        var monacoRef = null;
        var ready = false;
        var pendingValue = '';
        var suppressInput = false;
        var listeners = { input: [], scroll: [], keydown: [] };

        function emit(type, evt) {
            listeners[type].forEach(function (cb) {
                try { cb(evt); } catch (e) { /* ignore */ }
            });
        }

        var adapter = {
            isMonaco: true,

            get value() {
                return editor ? editor.getValue() : pendingValue;
            },
            set value(v) {
                var str = v == null ? '' : String(v);
                if (editor) {
                    suppressInput = true;
                    editor.setValue(str);
                    suppressInput = false;
                } else {
                    pendingValue = str;
                }
            },

            get selectionStart() {
                if (!editor) return 0;
                var m = editor.getModel();
                var s = editor.getSelection();
                return m && s ? m.getOffsetAt(s.getStartPosition()) : 0;
            },
            get selectionEnd() {
                if (!editor) return 0;
                var m = editor.getModel();
                var s = editor.getSelection();
                return m && s ? m.getOffsetAt(s.getEndPosition()) : 0;
            },
            setSelectionRange: function (start, end) {
                if (!editor) return;
                var m = editor.getModel();
                if (!m) return;
                var sp = m.getPositionAt(start);
                var ep = m.getPositionAt(end);
                editor.setSelection(monacoRef.Range.fromPositions(sp, ep));
                editor.revealPositionInCenterIfOutsideViewport(ep);
            },

            focus: function () { if (editor) editor.focus(); },
            layout: function () { if (editor) editor.layout(); },

            get scrollTop() { return editor ? editor.getScrollTop() : 0; },
            set scrollTop(v) { if (editor) editor.setScrollTop(v); },
            get scrollHeight() { return editor ? editor.getScrollHeight() : 0; },
            get clientHeight() { return editor ? editor.getLayoutInfo().height : 0; },

            addEventListener: function (type, cb) {
                if (listeners[type]) listeners[type].push(cb);
            },
            removeEventListener: function (type, cb) {
                if (!listeners[type]) return;
                var i = listeners[type].indexOf(cb);
                if (i >= 0) listeners[type].splice(i, 1);
            },

            getMonaco: function () { return editor; }
        };

        loadMonaco().then(function (monaco) {
            monacoRef = monaco;
            defineTheme(monaco);
            registerProviders(monaco);

            editor = monaco.editor.create(host, {
                value: pendingValue,
                language: 'markdown',
                theme: opts.theme || 'kb-dark',
                wordWrap: 'on',
                minimap: { enabled: opts.minimap !== false },
                lineNumbers: 'on',
                fontSize: opts.fontSize || 14,
                fontFamily: "'Monaco', 'Menlo', 'Ubuntu Mono', monospace",
                automaticLayout: true,
                scrollBeyondLastLine: false,
                renderWhitespace: 'selection',
                quickSuggestions: { other: true, comments: true, strings: true },
                suggestOnTriggerCharacters: true,
                tabSize: 2,
                padding: { top: 12, bottom: 12 }
            });

            ready = true;

            editor.onDidChangeModelContent(function () {
                if (!suppressInput) emit('input', { target: adapter });
            });

            editor.onDidScrollChange(function () {
                emit('scroll', { target: adapter });
            });

            editor.onKeyDown(function (e) {
                if (e.keyCode === monaco.KeyCode.Enter && !e.shiftKey && !isSuggestVisible(editor)) {
                    if (handleListEnter(monaco, editor, e)) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                }
            });

            editor.addAction({
                id: 'kb.toggleFullscreen',
                label: 'Toggle Editor Fullscreen',
                keybindings: [monaco.KeyCode.F11],
                run: function () {
                    if (typeof opts.onToggleFullscreen === 'function') {
                        opts.onToggleFullscreen();
                        setTimeout(function () { editor.layout(); }, 60);
                    }
                }
            });

            if (typeof opts.onReady === 'function') opts.onReady(adapter);
        }).catch(function (err) {
            console.error('Monaco failed to load:', err);
        });

        return adapter;
    }

    window.KBMonaco = { create: createAdapter, load: loadMonaco };
})();
