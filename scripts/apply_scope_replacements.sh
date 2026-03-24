#!/usr/bin/env bash

set -Eeuo pipefail

if [[ "${1:-}" == "" ]]; then
    exit 1
fi

PLUGIN_ROOT="$1"

PLUGIN_ROOT="$PLUGIN_ROOT" php <<'PHP'
<?php

declare(strict_types=1);

$pluginRootInput = getenv('PLUGIN_ROOT') ?: '';
$pluginRoot = realpath($pluginRootInput);
if ($pluginRoot === false) {
    exit(1);
}

$requiredPaths = [
    $pluginRoot . '/scoper-namespaces.php',
    $pluginRoot . '/src',
];

foreach ($requiredPaths as $requiredPath) {
    if (!file_exists($requiredPath)) {
        exit(1);
    }
}

$scopeConfigPath = $pluginRoot . '/scoper-namespaces.php';
$scopeConfig = require_once $scopeConfigPath;
$patterns = $scopeConfig['code_replacement_patterns'] ?? [];

if (!is_array($patterns) || $patterns === []) {
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
        exit(1);
    }

    $updated = preg_replace(array_keys($patterns), array_values($patterns), $content);
    if ($updated === null) {
        exit(1);
    }

    if ($updated !== $content && file_put_contents($file, $updated) === false) {
        exit(1);
    }
}
PHP
