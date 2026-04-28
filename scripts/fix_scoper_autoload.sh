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

$sourcePrefixReplacements = [
    "'Psr\\Log\\\\' =>" => "'TransbankVendor\\Psr\\Log\\\\' =>",
    "'Psr\\Http\\Message\\\\' =>" => "'TransbankVendor\\Psr\\Http\\Message\\\\' =>",
    "'Psr\\Http\\Client\\\\' =>" => "'TransbankVendor\\Psr\\Http\\Client\\\\' =>",
    "'GuzzleHttp\\Psr7\\\\' =>" => "'TransbankVendor\\GuzzleHttp\\Psr7\\\\' =>",
    "'GuzzleHttp\\Promise\\\\' =>" => "'TransbankVendor\\GuzzleHttp\\Promise\\\\' =>",
];

if (!is_array($replacements)) {
    exit(1);
}

function writeError(string $message): void
{
    $stderr = fopen('php://stderr', 'wb');
    if ($stderr !== false) {
        fwrite($stderr, $message);
        fclose($stderr);
        return;
    }

    error_log(rtrim($message));
}

$requiredPrefixedPrefixKeys = [
    "'TransbankVendor\\Psr\\Log\\\\' =>",
    "'TransbankVendor\\Psr\\Http\\Message\\\\' =>",
    "'TransbankVendor\\Psr\\Http\\Client\\\\' =>",
    "'TransbankVendor\\GuzzleHttp\\Psr7\\\\' =>",
    "'TransbankVendor\\GuzzleHttp\\Promise\\\\' =>",
];

$forbiddenUnprefixedPrefixKeys = [
    "'Psr\\\\Log\\\\' =>",
    "'Psr\\\\Http\\\\Message\\\\' =>",
    "'Psr\\\\Http\\\\Client\\\\' =>",
    "'GuzzleHttp\\\\Psr7\\\\' =>",
    "'GuzzleHttp\\\\Promise\\\\' =>",
];

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

    $updated = str_replace(
        array_merge(array_keys($replacements), array_keys($sourcePrefixReplacements)),
        array_merge(array_values($replacements), array_values($sourcePrefixReplacements)),
        $content
    );

    if ($updated !== $content && file_put_contents($file, $updated) === false) {
        exit(1);
    }

    foreach ($requiredPrefixedPrefixKeys as $prefixKey) {
        if (strpos($updated, $prefixKey) === false) {
            writeError("Missing required prefixed namespace key in autoload map: {$prefixKey} ({$file})\n");
            exit(1);
        }
    }

    foreach ($forbiddenUnprefixedPrefixKeys as $prefixKey) {
        if (strpos($updated, $prefixKey) !== false) {
            writeError("Found forbidden unprefixed namespace key in autoload map: {$prefixKey} ({$file})\n");
            exit(1);
        }
    }
}
PHP
