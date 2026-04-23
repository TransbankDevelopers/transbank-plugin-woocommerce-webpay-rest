<?php

declare(strict_types=1);

function tbkLoadPluginAutoloader(string $pluginRoot): void
{
    $prefixedAutoload = $pluginRoot . 'vendor-prefixed/autoload.php';
    if (!file_exists($prefixedAutoload)) {
        require_once $pluginRoot . 'vendor/autoload.php';
        return;
    }

    $scoperAutoload = $pluginRoot . 'vendor-prefixed/scoper-autoload.php';
    if (file_exists($scoperAutoload)) {
        require_once $scoperAutoload;
    }

    require_once $prefixedAutoload;
}
