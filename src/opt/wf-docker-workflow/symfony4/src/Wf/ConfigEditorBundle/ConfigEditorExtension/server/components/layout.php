<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $projectPath; ?> | Config editor</title>
    <link rel="stylesheet" href="/css/jquery.toastmessage.css" />
    <link rel="stylesheet" href="/js/jqueryfiletree/jQueryFileTree.min.css" />
    <link rel="stylesheet" href="/js/bootstrap-4.3.1-dist/css/bootstrap.css" />
    <link rel="stylesheet" href="/css/main.css" />
</head>
<body>
<div id="container">
    <div id="sidebar"></div>
    <div id="editors">
        <div id="buttons" class="btn-group btn-group-sm float-right" role="group">
            <button class="save btn btn-secondary disabled">Save</button>
            <button class="save-all btn btn-secondary disabled">Save all</button>
            <button class="fold-all btn btn-secondary">Fold all</button>
            <button class="unfold-all btn btn-secondary">Unfold all</button>
        </div>
        <ul class="nav nav-tabs" role="tablist"></ul>
        <div class="tab-content"></div>
    </div>
    <div id="help">
        <pre class="reference"></pre>
    </div>
</div>

<script src="/js/jquery-3.3.1.min.js"></script>
<script src="/js/bootstrap-4.3.1-dist/js/bootstrap.js"></script>
<script src="/js/jquery.toastmessage.js"></script>
<script src="/js/jqueryfiletree/jQueryFileTree.min.js"></script>
<!-- load ace -->
<script src="/js/ace-noconflict/ace.js"></script>
<!-- load ace language tools -->
<script src="/js/ace-noconflict/ext-language_tools.js"></script>
<!-- load ace modelist extension -->
<script src="/js/ace-noconflict/ext-modelist.js"></script>
<script src="/js/editor.js"></script>
<script>
    // Insert the config json
    var compConfig = <?php include sprintf('%s/%s/%s/%s', $projectPath, $wfConfigDir, 'config_editor', 'full_config.json'); ?>;
    var availableParameters = <?php include sprintf('%s/%s/%s/%s', $projectPath, $wfConfigDir, 'config_editor', 'placeholders.json'); ?>;
    var availableDockerComposeServices = <?php include sprintf('%s/%s/%s/%s', $projectPath, $wfConfigDir, 'config_editor', 'services.json'); ?>;
    $(document).ready( function() {
        $('#sidebar').fileTree({ root: '/', script: 'components/filetree.php'}, function(file) {
            loadFile(file);
        });
        openHelpReference();
        // Load base file
        loadFile('/<?php echo $baseConfigFile; ?>');
        // Init tabs
        // Close icon: removing the tab on click
        $(document).on("click", ".closeTab", function () {
            //there are multiple elements which has .closeTab icon so close the tab whose close icon is clicked
            var tabContentId = $(this).parent().attr("href");
            $(this).parent().parent().remove(); //remove li of tab
            // $('#myTab a:last').tab('show'); // Select first tab
            $(tabContentId).remove(); //remove respective tab content
            reset();
        });
        $(document).on("shown.bs.tab", '#editors a[data-toggle="tab"]', function() {
            reset();
            refreshUnsavedTabs();
        });
        $('#buttons .save').on('click', function() {
            if (!$(this).hasClass('disabled')) {
                $(this).addClass('disabled');
                saveActiveTab();
            }
        });
        $('#buttons .save-all').on('click', function() {
            if (!$(this).hasClass('disabled')) {
                $(this).addClass('disabled');
                saveAll();
            }
        });
        $('#buttons .fold-all').on('click', function() {
            getActiveEditor().getSession().foldAll();
        });
        $('#buttons .unfold-all').on('click', function() {
            getActiveEditor().getSession().unfold();
        });
        $(window).on('beforeunload', function() {
            return hasUnsavedContent() && confirm("There is/are unsaved content(s)! Are you sure you want to leave?");
        });
    });
</script>

</body>
</html>
