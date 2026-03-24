<?php

declare(strict_types=1);

$defaultPluginRoot = realpath(__DIR__ . '/../plugin');
if ($defaultPluginRoot === false) {
    fwrite(STDERR, "Cannot resolve default plugin root.\n");
    exit(1);
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/fix_scoper_autoload.php <plugin-root>\n");
    exit(1);
}

$pluginRoot = realpath($argv[1]);
$pluginRoot = $pluginRoot === false ? '' : $pluginRoot;

$scopeConfigPath = __DIR__ . '/../plugin/scoper-namespaces.php';

if ($pluginRoot !== $defaultPluginRoot) {
    fwrite(STDERR, "Invalid plugin root: {$argv[1]}\n");
    exit(1);
}

$composerDir = $pluginRoot . '/vendor-prefixed/composer';

if (!is_file($scopeConfigPath)) {
    fwrite(STDERR, "Missing scope config: {$scopeConfigPath}\n");
    exit(1);
}

$scopeConfig = require_once __DIR__ . '/../plugin/scoper-namespaces.php';
$replacements = $scopeConfig['autoload_replacements'] ?? [];

if (!is_array($replacements) || $replacements === []) {
    fwrite(STDERR, "Invalid autoload replacements in {$scopeConfigPath}\n");
    exit(1);
}

$targets = [
    $composerDir . '/autoload_psr4.php',
    $composerDir . '/autoload_static.php',
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

    if ($updated !== $content) {
        $ok = file_put_contents($file, $updated);
        if ($ok === false) {
            fwrite(STDERR, "Cannot write file: {$file}\n");
            exit(1);
        }
    }
}
