<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\Plugin\Helpers\PluginLogger;
use Transbank\Plugin\Model\LogConfig;

define('Transbank_webpay_Rest_Webpay_UPLOADS', untrailingslashit(wp_upload_dir()['basedir'] . '/transbank_webpay_plus_rest'));

class TbkFactory
{
    public static function createLogger()
    {
        $config = new LogConfig(Transbank_webpay_Rest_Webpay_UPLOADS .'/logs');
        return new PluginLogger($config);
    }
}

