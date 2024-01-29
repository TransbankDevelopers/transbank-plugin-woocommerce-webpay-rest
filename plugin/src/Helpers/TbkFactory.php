<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\Plugin\Helpers\PluginLogger;
use Transbank\Plugin\Model\LogConfig;
use Transbank\WooCommerce\WebpayRest\OneclickTransbankSdk;
use Transbank\WooCommerce\WebpayRest\WebpayplusTransbankSdk;

define(
    'TRANSBANK_WEBPAY_REST_UPLOADS',
    untrailingslashit(wp_upload_dir()['basedir'] . '/transbank_webpay_plus_rest')
);

class TbkFactory
{
    public static function createLogger()
    {
        $config = new LogConfig(TRANSBANK_WEBPAY_REST_UPLOADS .'/logs');
        return new PluginLogger($config);
    }

    public static function createWebpayplusTransbankSdk()
    {
        $config = get_option(WebpayplusTransbankSdk::OPTION_KEY);
        return new WebpayplusTransbankSdk(static::createLogger(),
            $config['webpay_rest_environment'],
            $config['webpay_rest_commerce_code'],
            $config['webpay_rest_api_key']
        );
    }

    public static function createOneclickTransbankSdk()
    {
        $config = get_option(OneclickTransbankSdk::OPTION_KEY);
        return new OneclickTransbankSdk(static::createLogger(),
            $config['environment'],
            $config['commerce_code'],
            $config['api_key'],
            $config['child_commerce_code']
        );
    }

}
