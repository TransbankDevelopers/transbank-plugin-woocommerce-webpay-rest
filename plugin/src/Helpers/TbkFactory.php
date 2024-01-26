<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\Plugin\Helpers\PluginLogger;
use Transbank\Plugin\Helpers\StringUtils;
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
        return new WebpayplusTransbankSdk(static::createLogger(),
            static::getVar('webpay_rest_environment'),
            static::getVar('webpay_rest_commerce_code'),
            static::getVar('webpay_rest_api_key')
        );
    }

    public static function createOneclickTransbankSdk()
    {
        return new OneclickTransbankSdk(static::createLogger(),
            static::getVar('environment'),
            static::getVar('commerce_code'),
            static::getVar('api_key'),
            static::getVar('child_commerce_code')
        );
    }

    private static function getVar($name){
        $value = get_option($name);
        if (!StringUtils::isNotBlankOrNull($value)){
            return '';
        }
        return $value;
    }
}
