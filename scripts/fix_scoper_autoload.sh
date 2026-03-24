#!/usr/bin/env bash

set -Eeuo pipefail

if [[ "${1:-}" == "" ]]; then
    echo "Usage: bash scripts/fix_scoper_autoload.sh <plugin-root>" 1>&2
    exit 1
fi

PLUGIN_ROOT="$1"

php <<'PHP' "$PLUGIN_ROOT"
<?php

declare(strict_types=1);

$pluginRoot = realpath($argv[1]);
$pluginRoot = $pluginRoot === false ? '' : $pluginRoot;

if (
    $pluginRoot === '' ||
    !is_file($pluginRoot . '/scoper-namespaces.php') ||
    !is_dir($pluginRoot . '/vendor-prefixed/composer')
) {
    fwrite(STDERR, "Invalid plugin root: {$argv[1]}\n");
    exit(1);
}

$scopeConfigPath = $pluginRoot . '/scoper-namespaces.php';
$scopeConfig = require $scopeConfigPath;
$replacements = $scopeConfig['autoload_replacements'] ?? [];

if (!is_array($replacements) || $replacements === []) {
    fwrite(STDERR, "Invalid autoload replacements in {$scopeConfigPath}\n");
    exit(1);
}

$targets = [
    $pluginRoot . '/vendor-prefixed/composer/autoload_psr4.php',
    $pluginRoot . '/vendor-prefixed/composer/autoload_static.php',
];

foreach ($targets as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Missing autoload file: {$file}\n");
        exit(1);
    }

    $content = file_get_contents($file);
    if ($content === false) {
        fwrite(STDERR, "Cannot read file: {$file}\n");
        exit(1);
    }

    $updated = str_replace(array_keys($replacements), array_values($replacements), $content);

    if ($updated !== $content && file_put_contents($file, $updated) === false) {
        fwrite(STDERR, "Cannot write file: {$file}\n");
        exit(1);
    }
}
PHP
