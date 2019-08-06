// trigger extension
var langTools = ace.require("ace/ext/language_tools"),
    modelist = ace.require("ace/ext/modelist"),
    editors = [],
    lastSavedText = []
;

function getActiveId() {
    return $('#editors .tab-content .active').first().attr('id');
}

function getActiveEditor() {
    var id = getActiveId();

    return editors[id];
}

/**
 * Create "Help" tab.
 */
function openHelpReference() {
    var editor = ace.edit(),
        theme = typeof sessionStorage.getItem('wf-theme') !== 'string'
            ? 'ace/theme/cobalt'
            : sessionStorage.getItem('wf-theme'),
        mode = 'ace/mode/yaml',
        hashId = btoa('help').replace(/=+$/, ''),
        panel,
        index = getTabIndex('help')
    ;
    if (index === -1) {
        editor.setOptions({
            theme: theme,
            mode: mode
        });
        editor.commands.addCommand({
            name: "save",
            exec: function() {/* do nothing, this is the help tab */},
            bindKey: { win: "ctrl-s", mac: "cmd-s" }
        });
        // Register help content
        var ymlHelpContent = '';
        $.each(compConfig.children, function (key, value) {
            ymlHelpContent += value.hasOwnProperty('reference') ? value.reference : '';
        });
        editor.session.setValue(ymlHelpContent.trim().replace(/<\/?(comment|info)>/mg, ''));

        panel = document.createElement('div');
        panel.setAttribute('id', hashId);
        panel.setAttribute('class', 'tab-pane');
        panel.setAttribute('role', 'tabpanel');
        panel.appendChild(editor.container);
        document.getElementById('editors').getElementsByClassName('tab-content')[0].appendChild(panel);
        $('#editors ul').prepend('<li class="nav-item"><a href="#' + hashId + '" class="nav-link" data-toggle="tab" role="tab" aria-controls="' + hashId + '">Help</a></li>');
        editors[hashId] = editor;
    }
}

/**
 *
 * @param filePath
 * @param content
 */
function openFile(filePath, content) {
    var editor = ace.edit(),
        theme = typeof sessionStorage.getItem('wf-theme') !== 'string'
            ? 'ace/theme/cobalt'
            : sessionStorage.getItem('wf-theme'),
        mode = filePath.indexOf('.yml') > 0
            ? 'ace/mode/yaml'
            : modelist.getModeForPath(filePath).mode,
        // base64 encoded filepath without '='
        hashId = btoa(filePath).replace(/=+$/, ''),
        panel,
        index = getTabIndex(filePath)
    ;
    // We open it if it hasn't opened yet.
    if (index === -1) {
        // enable autocompletion and snippets
        editor.setOptions({
            theme: theme,
            mode: mode,
            enableBasicAutocompletion: true,
            enableSnippets: true,
            enableLiveAutocompletion: false
        });
        editor.commands.addCommand({
            name: "save",
            exec: saveActiveTab,
            bindKey: { win: "ctrl-s", mac: "cmd-s" }
        });
        editor.session.setValue(content);
        // We save the loaded content to detect base file changes on the disk.
        lastSavedText[hashId] = content;

        panel = document.createElement('div');
        panel.setAttribute('id', hashId);
        panel.setAttribute('class', 'tab-pane');
        panel.setAttribute('role', 'tabpanel');
        panel.appendChild(editor.container);

        document.getElementById('editors').getElementsByClassName('tab-content')[0].appendChild(panel);
        if (mode === 'ace/mode/yaml') {
            // Register helper
            editor.selection.on("changeCursor", function (event, selection) {
                if (selection.$isEmpty) {
                    showHelp(editor);
                } else {
                    hideHelp();
                }
            });
        }
        editor.session.on("change", function() {
            refreshUnsavedTabs();
        });
        $('#editors ul').append('<li class="nav-item"><a href="#' + hashId + '" class="nav-link" data-toggle="tab" role="tab" aria-controls="' + hashId
            + '"><button class="close closeTab" type="button" >×</button><span class="file">' + filePath + '</span></a></li>');
        index = getTabIndex(filePath);
    }
    reset();
    $('#editors > ul > li:last-child a').tab('show');
    editors[hashId] = editor;
}

function loadFile(filePath) {
    $.get('/components/filecontents.php?file=' + filePath, function(content) {
        openFile(filePath, content);
    });
}

function save(hashId) {
    var filePath = atob(hashId),
        newContent = editors[hashId].session.getValue();
    $.get('/components/filecontents.php?file=' + filePath, function(originalContent) {
        if (originalContent.trim() === lastSavedText[hashId].trim() || confirm('The original file has been changed since you opened, do you really want to override it?')) {
            $.post('/components/save.php', {file: filePath, content: newContent}, function (data) {
                if (newContent === data) {
                    // @todo (Chris) Ide kellene vmi visszajelzés státuszbáron.
                    lastSavedText[hashId] = data;
                    refreshUnsavedTabs();
                } else {
                    window.alert('Something went wrong! The content isn\'t saved!');
                }
            });
        }
    });
}

function saveActiveTab() {
    var hashId = getActiveId();
    save(hashId);
}

function saveAll() {
    var hashId, originalContent, tab, currentContent;
    for (hashId in lastSavedText) {
        originalContent = lastSavedText[hashId];
        tab = getTabById(hashId);
        if (tab.length === 1 && editors.hasOwnProperty(hashId)) {
            currentContent = editors[hashId].session.getValue();
            if (originalContent !== currentContent) {
                save(hashId);
            }
        }
    }
}

function refreshUnsavedTabs() {
    var hashId, originalContent, tab, currentContent, isUnsaved=false, currentIsUnsaved=false, activeHashId=getActiveId();
    for (hashId in lastSavedText) {
        originalContent = lastSavedText[hashId];
        tab = getTabById(hashId);
        if (tab.length === 1 && editors.hasOwnProperty(hashId)) {
            currentContent = editors[hashId].session.getValue();
            if (originalContent !== currentContent) {
                tab.addClass('unsaved');
                isUnsaved = true;
                if (hashId === activeHashId) {
                    currentIsUnsaved = true;
                }
            } else {
                tab.removeClass('unsaved');
            }
        }
    }

    if (currentIsUnsaved) {
        $('#buttons .save').removeClass('disabled');
    } else {
        $('#buttons .save').addClass('disabled');
    }
    if (isUnsaved) {
        $('#buttons .save-all').removeClass('disabled');
    } else {
        $('#buttons .save-all').addClass('disabled');
    }
}

function hasUnsavedContent() {
    var hashId, originalContent, tab, currentContent, isUnsaved=false, currentIsUnsaved=false, activeHashId=getActiveId();
    for (hashId in lastSavedText) {
        originalContent = lastSavedText[hashId];
        tab = getTabById(hashId);
        if (tab.length === 1 && editors.hasOwnProperty(hashId)) {
            currentContent = editors[hashId].session.getValue();
            if (originalContent !== currentContent) {
                return true;
            }
        }
    }

    return false;
}

function getTabIndex(filePath) {
    return getTabIndexById(btoa(filePath).replace(/=+$/, ''));
}

function getTabIndexById(hashId) {
    return getTabById(hashId).index();
}

function getTabById(hashId) {
    return $('#editors > ul').find('a[href="#' + hashId + '"]').parent();
}

function reset() {
    hideHelp();
    // If there isn't active tab
    if ($('#editors ul').find('.active').length === 0) {
        $('#editors > ul > li:last-child a').tab('show');
    }
    if (typeof getActiveEditor() !== 'undefined') {
        getActiveEditor().focus();
    }
}

// Parse all of the file. Collect "meta.tag"-s and "bracket"-s.
function parseTree(editor) {
    var s = editor.session,
        lines = s.bgTokenizer.lines,
        currentPath = [],
        tree = [],
        currentDepth,
        // Open bracket: +1 , close bracket: -1
        bracketDepth = 0,
        // fallow the column
        currentColumn = 0,
        tokens;

    for (var i=0;i<lines.length;i++) {
        tokens = lines[i];
        // IF it isn't array OR empty OR the first tag isn't meta.tag...
        if (!$.isArray(tokens) || tokens.length === 0 || tokens[0].type !== 'meta.tag') {
            continue;
        }
        // We use this calculation only if we are out of all brackets.
        if (bracketDepth === 0) {
            currentDepth = parseInt( (tokens[0].value.split(' ').length - 1) / s.getTabSize() );
        }

        currentColumn = 0;
        for (var t=0;t<tokens.length;t++) {
            switch (tokens[t].type) {
                case 'meta.tag':
                    currentPath = currentPath.slice(0, currentDepth + bracketDepth);
                    currentPath.push(tokens[t].value.trim());
                    tree.push({
                        type: 'node',
                        path: currentPath,
                        depth: currentDepth + bracketDepth,
                        startRow: i,
                        startColumn: currentColumn + currentDepth * s.getTabSize()
                    });
                    break;
                case 'paren.lparen':
                    bracketDepth++;
                    tree.push({
                        type: 'openBracket',
                        path: currentPath,
                        depth: currentDepth + bracketDepth,
                        startRow: i,
                        startColumn: currentColumn
                    });
                    break;
                case 'paren.rparen':
                    bracketDepth--;
                    tree.push({
                        type: 'closeBracket',
                        path: currentPath,
                        depth: currentDepth + bracketDepth,
                        startRow: i,
                        startColumn: currentColumn
                    });
                    break;
            }
            currentColumn += tokens[t].value.length;
        }
    }

    return tree;
}

/**
 * There are two different options to create "key":
 *
 * <code>
 *     key: value
 *     parent: { key: value, foo: bar }
 * </code>
 *
 * So we have to use different ways that depend on brackets.
 */
function getCurrentPositionDepth(editor, tree, row, column) {
    var s = editor.session,
        last,
        bracketDepth = 0;

    for (var i=0;i<tree.length;i++) {
        if (tree[i].startRow >= row && tree[i].startColumn > column) {
            break;
        }
        switch (tree[i].type) {
            case 'openBracket':
                bracketDepth++;
                last = tree[i];
                break;
            case 'closeBracket':
                bracketDepth--;
                last = tree[i];
                break;
        }
    }

    return bracketDepth === 0
        ? parseInt(column/s.getTabSize())
        : last.depth;
}

/**
 * Finds the "parent node" from cursor position. We use it to find autocomplete and used words.
 *
 * @param editor
 * @param tree      Array
 * @param row       Int
 * @param column    Int
 * @returns {Array}
 */
function getParentMetaTagPath(editor, tree, row, column) {
    var s = editor.session,
        currentDepth = getCurrentPositionDepth(editor, tree, row, column),
        last = [];
    // Find parent
    for (var i=0;i<tree.length;i++) {
        if (tree[i].startRow > row || (tree[i].startRow === row && tree[i].startColumn > column)) {
            return last;
        }
        if (tree[i].path.length <= currentDepth) {
            last = tree[i].path;
        }
    }

    return last;
}

/**
 * Gets the last meta.tag/key path from cursor position. We use it to find and show help context.
 *
 * @param editor
 * @param tree
 * @param row
 * @param column
 * @returns {Array}
 */
function getLastMetaTagPath(editor, tree, row, column) {
    var s = editor.session,
        last = [];
    // Find parent
    for (var i=0;i<tree.length;i++) {
        if (tree[i].startRow > row || (tree[i].startRow === row && tree[i].startColumn > column)) {
            return last;
        }
        last = tree[i].path;
    }

    return last;
}

/**
 * Finds the used words to skip them at autocomplete.
 *
 * @param tree
 * @param pathArray
 * @returns {Array}
 */
function getUsedWords(tree, pathArray) {
    var usedWords = [];
    for (var i=0;i<tree.length;i++) {
        if (tree[i].path.length === pathArray.length + 1) {
            if (JSON.stringify(tree[i].path.slice(0, pathArray.length)) === JSON.stringify(pathArray)) {
                usedWords.push(tree[i].path.slice(-1)[0]);
            }
        }
    }

    return usedWords;
}

function isUnusedPosition(editor, row, column) {
    var tokens = editor.session.getTokens(row),
        currentColumn = 0,
        lastTokenType = null;
    for (var i=0;i<tokens.length;i++) {
        lastTokenType = tokens[i].type;
        currentColumn += tokens[i].value.length;
        if (currentColumn >= column) {
            break;
        }
    }

    return tokens.length === 0 || lastTokenType === 'comment';
}

/**
 * Tries to find the config node with the pathArray.
 *
 * @param pathArray
 * @returns {*}
 */
function getConfigNode(pathArray) {
    var current = compConfig, key;
    for (var i=0;i<pathArray.length;i++) {
        key = pathArray[i];
        // Base option: current.children.[...] exists
        if (typeof current === 'object' && current.hasOwnProperty('children') && current.children.hasOwnProperty(key)) {
            current = current.children[key];
            // If it is a prototype
        } else if (typeof current === 'object' && current.hasOwnProperty('prototype') && current.prototype.length === 1) {
            key = current.prototype[0];
            current = current.children[key];
        } else {
            return null;
        }
    }

    return current;
}

/**
 * Gets node children if them exist.
 *
 * @param pathArray
 * @returns {null}
 */
function getConfigEnvironment(pathArray) {
    var current = getConfigNode(pathArray);

    if (typeof current === 'object' && current !== null && current.hasOwnProperty('children') && !current.hasOwnProperty('prototype')) {
        return current.children;
    }

    return null;
}

/**
 * Gets usable config words.
 *
 * @param env
 * @param usedWords
 * @returns {Array}
 *
 * @todo (Chris) Import esetén beletehetnénk az elérhető fájlokat
 */
function getConfigWords(env, usedWords) {
    var words = [], suffix;
    if (env === null) {
        // Maybe we are in string, it can't be "array key".
        $.each(availableParameters, function (key, value) {
            words.push({
                name: value,
                value: value,
                score: 150,
                meta: "placeholder"
            })
        });
    } else {
        $.each(env, function (key, value) {
            if (usedWords.indexOf(key) === -1) {
                // Base suffix
                suffix = ":\n";
                // Overridden suffix
                if (value.hasOwnProperty('default')) {
                    // Changes default to simple ~ if it needs.
                    if (value.default === '~') {
                        suffix = ": ~\n";
                    } else {
                        suffix = ": " + JSON.stringify(value.default);
                    }
                }
                words.push({
                    name: key,
                    value: key + suffix,
                    score: value.required ? 300 : 200,
                    meta: value.required ? "required" : "optional"
                });
            }
        });
    }

    // Register the docker-compose services. It can be "array key", so it is a little bit different like placeholders
    $.each(availableDockerComposeServices, function (key, value) {
        words.push({
            name: value,
            value: value,
            score: 100,
            meta: "docker-compose service"
        })
    });

    return words;
}

function formatReference(reference) {
    return reference
        .trim()
        .replace(/(# Prototype.*\n *)([\w_-]+):/mg, '$1<span class="key"><span class="prototype">[$2]</span>:</span>')
        .replace(/<comment>/g, '<span class="comment">')
        .replace(/<\/comment>/g, '</span>')
        .replace(/<info>/g, '<span class="info">')
        .replace(/<\/info>/g, '</span>')
        .replace(/^( *)(\w+):/mg, '$1<span class="key">$2:</span>')
    ;
}
/**
 * Show the help if there is help content. Now we use only the `reference`, but there are other options:
 *  - reference
 *  - info
 *  - example
 *  - yaml_example
 */
function showHelp(editor) {
    var pos = editor.getCursorPosition();
    var tree, currentPositionPath, node, helpContainer = $('#help');
    if (!isUnusedPosition(editor, pos.row, pos.column)) {
        tree = parseTree(editor);
        currentPositionPath = getLastMetaTagPath(editor, tree, pos.row, pos.column);
        node = getConfigNode(currentPositionPath);

        // Hide help if there isn't information or it is the recipes root node.
        if (node === null || node.path === 'project.recipes' || !node.hasOwnProperty('reference')) {
            helpContainer.hide();
        } else {
            var content = formatReference(node.reference);
            helpContainer.find('.reference').html(content);
            helpContainer.show();
        }
    } else {
        helpContainer.hide();
    }
}
function hideHelp() {
    $('#help').hide();
}
var configCompleter = {
    getCompletions: function(editor, session, pos, prefix, callback) {
        var tree, wordList = [], currentPositionPath, configEnv, usedWords;
        if (session.getMode().$id === 'ace/mode/yaml') {
            tree = parseTree(editor);
            // Info: the pos.column is the current cursor position. We need the "start of the word", so we decrease it with length of prefix.
            currentPositionPath = getParentMetaTagPath(editor, tree, pos.row, pos.column - prefix.length);
            configEnv = getConfigEnvironment(currentPositionPath);
            usedWords = getUsedWords(tree, currentPositionPath);
            wordList = getConfigWords(configEnv, usedWords);
        }
        callback(null, wordList);
    }
};
// We don't use the addComplementer, because we want to remove the default 'local' complementer. We don't need for it.
langTools.setCompleters([configCompleter]);
