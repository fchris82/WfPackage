<?php declare(strict_types=1);

$projectPath = $_ENV['PWD'];
$filePath = realpath($projectPath . $_POST['file']);
$content = $_POST['content'];

// The last is a security check!
if (file_exists($filePath) && is_file($filePath) && 0 === strpos($filePath, $projectPath)) {
    file_put_contents($filePath, $content);
    echo $content;
} else {
    echo 'ERROR';
}
