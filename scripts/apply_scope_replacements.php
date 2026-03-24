<?php

declare(strict_types=1);

function failScopeReplacementScript(): void
{
    exit(1);
}

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(403);
    exit('Forbidden');
}

if ($argc < 2) {
    failScopeReplacementScript();
}

$pluginRoot = realpath($argv[1]);
if ($pluginRoot === false) {
    failScopeReplacementScript();
}

$requiredPaths = [
    $pluginRoot . '/scoper-namespaces.php',
    $pluginRoot . '/src',
];

foreach ($requiredPaths as $requiredPath) {
    if (!file_exists($requiredPath)) {
        failScopeReplacementScript();
    }
}

$scopeConfigPath = $pluginRoot . '/scoper-namespaces.php';
if (!is_file($scopeConfigPath)) {
    failScopeReplacementScript();
}

$scopeConfig = require $scopeConfigPath;
$patterns = $scopeConfig['code_replacement_patterns'] ?? [];
if (!is_array($patterns) || $patterns === []) {
    failScopeReplacementScript();
}

$paths = [
    $pluginRoot . '/src',
    $pluginRoot . '/shared',
    $pluginRoot . '/views',
];

$files = [];
foreach ($paths as $path) {
    if (is_file($path)) {
        $files[] = $path;
    } elseif (is_dir($path)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                $files[] = $fileInfo->getPathname();
            }
        }
    }
}

foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        failScopeReplacementScript();
    }

    $updated = preg_replace(array_keys($patterns), array_values($patterns), $content);
    if ($updated === null) {
        failScopeReplacementScript();
    }

    if ($updated !== $content) {
        $ok = file_put_contents($file, $updated);
        if ($ok === false) {
            failScopeReplacementScript();
        }
    }
}
