<?php

declare(strict_types=1);

function failFixScoperAutoloadScript(): void
{
    exit(1);
}

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(403);
    exit('Forbidden');
}

if ($argc < 2) {
    failFixScoperAutoloadScript();
}

$pluginRoot = realpath($argv[1]);
$pluginRoot = $pluginRoot === false ? '' : $pluginRoot;

if (
    $pluginRoot === '' ||
    !is_file($pluginRoot . '/scoper-namespaces.php') ||
    !is_dir($pluginRoot . '/vendor-prefixed/composer')
) {
    failFixScoperAutoloadScript();
}

$scopeConfigPath = $pluginRoot . '/scoper-namespaces.php';
if (!is_file($scopeConfigPath)) {
    failFixScoperAutoloadScript();
}

$scopeConfig = require $scopeConfigPath;
$replacements = $scopeConfig['autoload_replacements'] ?? [];

if (!is_array($replacements) || $replacements === []) {
    failFixScoperAutoloadScript();
}

$targets = [
    $pluginRoot . '/vendor-prefixed/composer/autoload_psr4.php',
    $pluginRoot . '/vendor-prefixed/composer/autoload_static.php',
];

foreach ($targets as $file) {
    if (!is_file($file)) {
        failFixScoperAutoloadScript();
    }

    $content = file_get_contents($file);
    if ($content === false) {
        failFixScoperAutoloadScript();
    }

    $updated = str_replace(array_keys($replacements), array_values($replacements), $content);

    if ($updated !== $content) {
        $ok = file_put_contents($file, $updated);
        if ($ok === false) {
            failFixScoperAutoloadScript();
        }
    }
}
