<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(403);
    exit('Forbidden');
}

$defaultPluginRoot = realpath(__DIR__ . '/../plugin');
if ($defaultPluginRoot === false) {
    fwrite(STDERR, "Cannot resolve default plugin root.\n");
    exit(1);
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/apply_scope_replacements.php <plugin-root>\n");
    exit(1);
}

$pluginRoot = realpath($argv[1]);
if ($pluginRoot === false || $pluginRoot !== $defaultPluginRoot) {
    fwrite(STDERR, "Invalid plugin root: {$argv[1]}\n");
    exit(1);
}

$scopeConfigPath = __DIR__ . '/../plugin/scoper-namespaces.php';
if (!is_file($scopeConfigPath)) {
    fwrite(STDERR, "Missing scope config: {$scopeConfigPath}\n");
    exit(1);
}

$scopeConfig = require_once __DIR__ . '/../plugin/scoper-namespaces.php';
$patterns = $scopeConfig['code_replacement_patterns'] ?? [];
if (!is_array($patterns) || $patterns === []) {
    fwrite(STDERR, "Invalid code replacement patterns in {$scopeConfigPath}\n");
    exit(1);
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
        fwrite(STDERR, "Cannot read file: {$file}\n");
        exit(1);
    }

    $updated = preg_replace(array_keys($patterns), array_values($patterns), $content);
    if ($updated === null) {
        fwrite(STDERR, "Regex replacement failed in file: {$file}\n");
        exit(1);
    }

    if ($updated !== $content) {
        $ok = file_put_contents($file, $updated);
        if ($ok === false) {
            fwrite(STDERR, "Cannot write file: {$file}\n");
            exit(1);
        }
    }
}
