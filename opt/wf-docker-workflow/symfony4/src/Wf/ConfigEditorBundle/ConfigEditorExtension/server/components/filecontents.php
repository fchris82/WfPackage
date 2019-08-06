<?php declare(strict_types=1);

$projectPath = $_ENV['PWD'];
$filePath = realpath($projectPath . $_GET['file']);

// The last is a security check!
echo file_exists($filePath) && is_file($filePath) && 0 === strpos($filePath, $projectPath)
    ? file_get_contents($filePath)
    : 'Missing file: ' . $filePath;
