<?php declare(strict_types=1);

$projectPath = $_ENV['PWD'];
$baseConfigFile = $_ENV['WF_CONFIGURATION_FILE_NAME'];
$wfConfigDir = $_ENV['WF_WORKING_DIRECTORY_NAME'];
$component = $_GET['page'] ?: 'layout';
$componentDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR;
if (!file_exists($componentDirectory . $component . '.php')) {
    $component = 'layout';
}
include $componentDirectory . $component . '.php';
