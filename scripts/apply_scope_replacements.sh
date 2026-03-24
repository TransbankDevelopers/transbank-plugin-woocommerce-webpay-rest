#!/usr/bin/env bash

set -Eeuo pipefail

if [[ "${1:-}" == "" ]]; then
    echo "Usage: bash scripts/apply_scope_replacements.sh <plugin-root>" 1>&2
    exit 1
fi

PLUGIN_ROOT="$1"

php <<'PHP' "$PLUGIN_ROOT"
<?php

declare(strict_types=1);

$pluginRoot = realpath($argv[1]);
if ($pluginRoot === false) {
    fwrite(STDERR, "Invalid plugin root: {$argv[1]}\n");
    exit(1);
}

$requiredPaths = [
    $pluginRoot . '/scoper-namespaces.php',
    $pluginRoot . '/src',
];

foreach ($requiredPaths as $requiredPath) {
    if (!file_exists($requiredPath)) {
        fwrite(STDERR, "Invalid plugin root: {$argv[1]}\n");
        exit(1);
    }
}

$scopeConfigPath = $pluginRoot . '/scoper-namespaces.php';
$scopeConfig = require $scopeConfigPath;
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

    if ($updated !== $content && file_put_contents($file, $updated) === false) {
        fwrite(STDERR, "Cannot write file: {$file}\n");
        exit(1);
    }
}
PHP
