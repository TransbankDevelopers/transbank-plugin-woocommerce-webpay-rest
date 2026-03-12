<?php

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/fix_scoper_autoload.php <plugin-root>\n");
    exit(1);
}

$pluginRoot = rtrim($argv[1], DIRECTORY_SEPARATOR);
$composerDir = $pluginRoot . '/vendor-prefixed/composer';
$scopeConfigPath = $pluginRoot . '/scoper-namespaces.php';

if (!is_file($scopeConfigPath)) {
    fwrite(STDERR, "Missing scope config: {$scopeConfigPath}\n");
    exit(1);
}

$scopeConfig = require_once $scopeConfigPath;
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
