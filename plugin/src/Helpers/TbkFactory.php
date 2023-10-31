<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\Plugin\Helpers\PluginLogger;
use Transbank\Plugin\Model\LogConfig;

class TbkFactory
{
    public static function createLogger()
    {
        $config = new LogConfig(dirname(dirname(__DIR__)).'/logs');
        return new PluginLogger($config);
    }
}

