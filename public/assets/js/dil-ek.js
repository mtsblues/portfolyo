/*
 * highlight.js ek dilleri: PowerShell, Dockerfile, Nginx, INI/.env.
 * Paketlenen highlight.js "common" sürümü bu dilleri içermez; bilgi kütüphanesi (sistem/BT yazıları) için gereklidir.
 * Bu dosya highlight.min.js'den SONRA, uygulama.js'den ÖNCE yüklenir. Kendi sunucunuzdan sunulur (CSP uyumlu).
 */
(function () {
    'use strict';
    var hljs = window.hljs;
    if (!hljs || typeof hljs.registerLanguage !== 'function') { return; }

    // ---- PowerShell ----
    hljs.registerLanguage('powershell', function (h) {
        var DEGISKEN = {
            className: 'variable',
            variants: [{ begin: /\$\{[^}]+\}/ }, { begin: /\$[A-Za-z_?^$][\w:]*/ }, { begin: /@[A-Za-z_]\w*/ }]
        };
        var ALT_IFADE = { className: 'subst', begin: /\$\(/, end: /\)/, contains: ['self'] };
        var DIZGE = {
            className: 'string',
            variants: [
                { begin: /@"/, end: /"@/, contains: [{ begin: /`./ }, DEGISKEN, ALT_IFADE] },
                { begin: /@'/, end: /'@/ },
                { begin: /"/, end: /"/, contains: [{ begin: /`./ }, DEGISKEN, ALT_IFADE] },
                { begin: /'/, end: /'/, contains: [{ begin: /''/ }] }
            ]
        };
        ALT_IFADE.contains = [DEGISKEN, DIZGE, h.C_NUMBER_MODE, { className: 'built_in', begin: /\b[A-Za-z]+-[A-Za-z]+\b/ }];
        return {
            name: 'PowerShell',
            aliases: ['ps', 'ps1', 'pwsh'],
            case_insensitive: true,
            keywords: {
                keyword: 'if else elseif switch foreach for while do until break continue return function filter param begin process end ' +
                    'try catch finally throw trap exit class enum using in data dynamicparam workflow parallel sequence inlinescript',
                literal: 'true false null'
            },
            contains: [
                h.COMMENT(/<#/, /#>/),
                h.COMMENT(/#/, /$/),
                DIZGE,
                DEGISKEN,
                { className: 'operator', begin: /\s-(?:eq|ne|gt|ge|lt|le|like|notlike|match|notmatch|contains|notcontains|in|notin|and|or|not|xor|replace|split|join|is|isnot|as|f|ceq|cne|ilike|imatch)\b/ },
                { className: 'built_in', begin: /\b[A-Z][A-Za-z]+-[A-Z][A-Za-z0-9]+\b/ },
                { className: 'attr', begin: /(?:^|\s)-[A-Za-z][\w]*/ },
                { className: 'type', begin: /\[[A-Za-z][\w.,\[\] ]*\]/ },
                h.C_NUMBER_MODE
            ]
        };
    });

    // ---- Dockerfile ----
    hljs.registerLanguage('dockerfile', function (h) {
        return {
            name: 'Dockerfile',
            aliases: ['docker'],
            case_insensitive: true,
            keywords: {
                keyword: 'from run cmd label maintainer expose env add copy entrypoint volume user workdir arg onbuild stopsignal healthcheck shell as'
            },
            contains: [
                h.HASH_COMMENT_MODE,
                h.APOS_STRING_MODE,
                h.QUOTE_STRING_MODE,
                h.NUMBER_MODE,
                { className: 'variable', begin: /\$\{?[A-Za-z_]\w*\}?/ },
                { className: 'attr', begin: /--[a-z-]+(?==|\s)/ }
            ]
        };
    });

    // ---- Nginx ----
    hljs.registerLanguage('nginx', function (h) {
        var DEGISKEN = { className: 'variable', variants: [{ begin: /\$\{[\w]+\}/ }, { begin: /\$[A-Za-z_]\w*/ }] };
        return {
            name: 'Nginx',
            aliases: ['nginxconf'],
            keywords: {
                keyword: 'server location upstream http events map if types geo stream mail limit_except',
                literal: 'on off'
            },
            contains: [
                h.HASH_COMMENT_MODE,
                { className: 'string', begin: /"/, end: /"/, contains: [DEGISKEN] },
                { className: 'string', begin: /'/, end: /'/, contains: [DEGISKEN] },
                { className: 'regexp', begin: /(?:~\*?|\^~)\s*[^\s;{]+/ },
                DEGISKEN,
                { className: 'number', begin: /\b\d+(?:\.\d+)?[kKmMgGsShHdDwWy]?b?\b/ },
                { className: 'attribute', begin: /^[\t ]*[a-z_][a-z0-9_]*/ }
            ]
        };
    });

    // ---- INI / .env / conf ----
    hljs.registerLanguage('ini', function (h) {
        return {
            name: 'INI',
            aliases: ['env', 'conf', 'cfg', 'properties'],
            case_insensitive: true,
            contains: [
                h.COMMENT(/^[ \t]*[;#]/, /$/),
                { className: 'section', begin: /^[ \t]*\[/, end: /\]/ },
                { className: 'attr', begin: /^[ \t]*[A-Za-z0-9_.$-]+(?=[ \t]*=)/ },
                {
                    begin: /=/, end: /$/, relevance: 0,
                    contains: [
                        h.COMMENT(/[ \t]+[;#]/, /$/),
                        { className: 'string', begin: /"/, end: /"/ },
                        { className: 'string', begin: /'/, end: /'/ },
                        { className: 'variable', begin: /\$\{?[A-Za-z_]\w*\}?/ },
                        { className: 'literal', begin: /\b(?:true|false|on|off|yes|no|null)\b/ },
                        { className: 'number', begin: /\b\d+(?:\.\d+)?\b/ }
                    ]
                }
            ]
        };
    });
})();
