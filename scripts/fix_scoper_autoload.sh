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
$pluginRoot = $pluginRoot === false ? '' : $pluginRoot;

if (
    $pluginRoot === '' ||
    !is_file($pluginRoot . '/scoper-namespaces.php') ||
    !is_dir($pluginRoot . '/vendor-prefixed/composer')
) {
    exit(1);
}

$scopeConfigPath = $pluginRoot . '/scoper-namespaces.php';
$scopeConfig = require_once $scopeConfigPath;
$replacements = $scopeConfig['autoload_replacements'] ?? [];

if (!is_array($replacements) || $replacements === []) {
    exit(1);
}

$targets = [
    $pluginRoot . '/vendor-prefixed/composer/autoload_psr4.php',
    $pluginRoot . '/vendor-prefixed/composer/autoload_static.php',
];

foreach ($targets as $file) {
    if (!is_file($file)) {
        exit(1);
    }

    $content = file_get_contents($file);
    if ($content === false) {
        exit(1);
    }

    $updated = str_replace(array_keys($replacements), array_values($replacements), $content);

    if ($updated !== $content && file_put_contents($file, $updated) === false) {
        exit(1);
    }
}
PHP
