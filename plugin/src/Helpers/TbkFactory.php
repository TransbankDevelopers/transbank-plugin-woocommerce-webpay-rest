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
        if (!isset($config)){
            $config = [];
        }
        $environment = isset($config['webpay_rest_environment']) ?
            $config['webpay_rest_environment'] : null;
        $commerceCode = isset($config['webpay_rest_commerce_code']) ?
            $config['webpay_rest_commerce_code'] : null;
        $apiKey = isset($config['webpay_rest_api_key']) ?
            $config['webpay_rest_api_key'] : null;
        $buyOrderFormat = isset($config['buy_order_format']) ?
            $config['buy_order_format'] : WebpayplusTransbankSdk::BUY_ORDER_FORMAT;
        return new WebpayplusTransbankSdk(static::createLogger(),
            $environment,
            $commerceCode,
            $apiKey,
            $buyOrderFormat
        );
    }

    public static function createOneclickTransbankSdk()
    {
        $config = get_option(OneclickTransbankSdk::OPTION_KEY);
        if (!isset($config)){
            $config = [];
        }
        $environment = isset($config['environment']) ?
            $config['environment'] : null;
        $commerceCode = isset($config['commerce_code']) ?
            $config['commerce_code'] : null;
        $apiKey = isset($config['api_key']) ?
            $config['api_key'] : null;
        $childCommerceCode = isset($config['child_commerce_code']) ?
            $config['child_commerce_code'] : null;
        $buyOrderFormat = isset($config['buy_order_format']) ?
            $config['buy_order_format'] : OneclickTransbankSdk::BUY_ORDER_FORMAT;
        $childBuyOrderFormat = isset($config['child_buy_order_format']) ?
            $config['child_buy_order_format'] : OneclickTransbankSdk::CHILD_BUY_ORDER_FORMAT;
        return new OneclickTransbankSdk(static::createLogger(),
            $environment,
            $commerceCode,
            $apiKey,
            $childCommerceCode,
            $buyOrderFormat,
            $childBuyOrderFormat
        );
    }

}
