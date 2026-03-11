<?php

declare(strict_types=1);

function tbkLoadPluginAutoloader(string $pluginRoot): void
{
    $prefixedAutoload = $pluginRoot . 'vendor-prefixed/autoload.php';
    if (!file_exists($prefixedAutoload)) {
        require_once $pluginRoot . 'vendor/autoload.php';
        return;
    }

    $runtimePrefixes = tbkLoadScopedRuntimePrefixes($pluginRoot);
    if ($runtimePrefixes !== []) {
        tbkRegisterScopedAutoloader($pluginRoot . 'vendor-prefixed/', $runtimePrefixes);
    }

    $scoperAutoload = $pluginRoot . 'vendor-prefixed/scoper-autoload.php';
    if (file_exists($scoperAutoload)) {
        require_once $scoperAutoload;
    }

    require_once $prefixedAutoload;
}

function tbkLoadScopedRuntimePrefixes(string $pluginRoot): array
{
    $scopeConfigPath = $pluginRoot . 'scoper-namespaces.php';
    if (!file_exists($scopeConfigPath)) {
        return [];
    }

    $scopeConfig = require_once $scopeConfigPath;
    $runtimePrefixes = $scopeConfig['runtime_psr4'] ?? [];

    return is_array($runtimePrefixes) ? $runtimePrefixes : [];
}

function tbkRegisterScopedAutoloader(string $prefixedRoot, array $runtimePrefixes): void
{
    spl_autoload_register(static function ($class) use ($prefixedRoot, $runtimePrefixes) {
        foreach ($runtimePrefixes as $prefix => $relativeBaseDir) {
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                continue;
            }

            $relativeClass = substr($class, strlen($prefix));
            $file = $prefixedRoot . $relativeBaseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }

            return;
        }
    }, true, true);
}
