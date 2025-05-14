<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\Plugin\Helpers\PluginLogger;
use Transbank\Plugin\Model\LogConfig;
use Transbank\Plugin\Model\WebpayplusConfig;
use Transbank\Plugin\Model\OneclickConfig;
use Transbank\WooCommerce\WebpayRest\OneclickTransbankSdk;
use Transbank\WooCommerce\WebpayRest\WebpayplusTransbankSdk;
use Transbank\Plugin\Repositories\TransactionRepositoryInterface;
use Transbank\Plugin\Repositories\InscriptionRepositoryInterface;
use Transbank\WooCommerce\WebpayRest\Repositories\TransactionRepository;
use Transbank\WooCommerce\WebpayRest\Repositories\InscriptionRepository;

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

    public static function getWebpayplusConfig(): WebpayplusConfig
    {
        $config = get_option(WebpayplusTransbankSdk::OPTION_KEY) ?? [];
        return new WebpayplusConfig([
            'environment' => $config['webpay_rest_environment'] ?? null,
            'commerceCode' => $config['webpay_rest_commerce_code'] ?? null,
            'apiKey' => $config['webpay_rest_api_key'] ?? null,
            'buyOrderFormat' => $config['buy_order_format'] ?? WebpayplusTransbankSdk::BUY_ORDER_FORMAT,
        ]);
    }

    public static function getOneclickConfig(): OneclickConfig
    {
        $config = get_option(OneclickTransbankSdk::OPTION_KEY) ?? [];
        return new OneclickConfig([
            'environment' => $config['environment'] ?? null,
            'commerceCode' => $config['commerce_code'] ?? null,
            'apiKey' => $config['api_key'] ?? null,
            'childCommerceCode' => $config['child_commerce_code'] ?? null,
            'buyOrderFormat' => $config['buy_order_format'] ?? OneclickTransbankSdk::BUY_ORDER_FORMAT,
            'childBuyOrderFormat' => $config['child_buy_order_format'] ?? OneclickTransbankSdk::CHILD_BUY_ORDER_FORMAT,
        ]);
    }

    public static function createWebpayplusTransbankSdk()
    {
        return new WebpayplusTransbankSdk(
            static::createLogger(),
            static::getWebpayplusConfig(),
            static::createTransactionRepository()
        );
    }

    public static function createOneclickTransbankSdk()
    {
        return new OneclickTransbankSdk(
            static::createLogger(),
            static::getOneclickConfig(),
            static::createTransactionRepository(),
            static::createInscriptionRepository()
        );
    }

    /**
     * Create and return an instance of the TransactionRepository.
     *
     * @return TransactionRepositoryInterface
     */
    public static function createTransactionRepository(): TransactionRepositoryInterface
    {
        return new TransactionRepository();
    }

    /**
     * Create and return an instance of the InscriptionRepository.
     *
     * @return InscriptionRepositoryInterface
     */
    public static function createInscriptionRepository(): InscriptionRepositoryInterface
    {
        return new InscriptionRepository();
    }

}


