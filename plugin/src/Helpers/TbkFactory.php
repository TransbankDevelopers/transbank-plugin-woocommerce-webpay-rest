<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\Plugin\Helpers\PluginLogger;
use Transbank\Plugin\Model\LogConfig;
use Transbank\Plugin\Model\WebpayplusConfig;
use Transbank\Plugin\Model\OneclickConfig;
use Transbank\Plugin\Repositories\TransactionRepositoryInterface;
use Transbank\Plugin\Repositories\InscriptionRepositoryInterface;
use Transbank\WooCommerce\WebpayRest\Repositories\TransactionRepository;
use Transbank\WooCommerce\WebpayRest\Repositories\InscriptionRepository;
use Transbank\WooCommerce\WebpayRest\Services\EcommerceService;
use Transbank\WooCommerce\WebpayRest\Services\WebpayService;
use Transbank\WooCommerce\WebpayRest\Services\OneclickInscriptionService;
use Transbank\WooCommerce\WebpayRest\Services\OneclickAuthorizationService;
use Transbank\WooCommerce\WebpayRest\Services\TransactionService;
use Transbank\WooCommerce\WebpayRest\Services\InscriptionService;


define(
    'TRANSBANK_WEBPAY_REST_UPLOADS',
    untrailingslashit(wp_upload_dir()['basedir'] . '/transbank_webpay_plus_rest')
);

class TbkFactory
{
    const WEBPAY_OPTION_KEY = 'woocommerce_transbank_webpay_plus_rest_settings';
    const ONECLICK_OPTION_KEY = 'woocommerce_transbank_oneclick_mall_rest_settings';
    public static function createLogger()
    {
        $config = new LogConfig(TRANSBANK_WEBPAY_REST_UPLOADS . '/logs');
        return new PluginLogger($config);
    }

    public static function getWebpayplusConfig(): WebpayplusConfig
    {
        $config = get_option(static::WEBPAY_OPTION_KEY) ?? [];
        return new WebpayplusConfig([
            'environment' => $config['webpay_rest_environment'] ?? null,
            'commerceCode' => $config['webpay_rest_commerce_code'] ?? null,
            'apiKey' => $config['webpay_rest_api_key'] ?? null,
            'buyOrderFormat' => $config['buy_order_format'] ?? WebpayService::BUY_ORDER_FORMAT,
            'statusAfterPayment' => $config['webpay_rest_after_payment_order_status'] ?? ''
        ]);
    }

    public static function getOneclickConfig(): OneclickConfig
    {
        $config = get_option(static::ONECLICK_OPTION_KEY) ?? [];
        return new OneclickConfig([
            'environment' => $config['environment'] ?? null,
            'commerceCode' => $config['commerce_code'] ?? null,
            'apiKey' => $config['api_key'] ?? null,
            'childCommerceCode' => $config['child_commerce_code'] ?? null,
            'buyOrderFormat' => $config['buy_order_format'] ?? OneclickAuthorizationService::BUY_ORDER_FORMAT,
            'childBuyOrderFormat' => $config['child_buy_order_format'] ?? OneclickAuthorizationService::CHILD_BUY_ORDER_FORMAT,
            'statusAfterPayment' => $config['oneclick_after_payment_order_status'] ?? ''
        ]);
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

    public static function createEcommerceService()
    {
        return new EcommerceService(
            static::createLogger(),
            static::getWebpayplusConfig(),
            static::getOneclickConfig()
        );
    }

    public static function createWebpayService()
    {
        return new WebpayService(
            static::createLogger(),
            static::getWebpayplusConfig()
        );
    }

    public static function createOneclickInscriptionService()
    {
        return new OneclickInscriptionService(
            static::createLogger(),
            static::getOneclickConfig()
        );
    }

    public static function createOneclickAuthorizationService()
    {
        return new OneclickAuthorizationService(
            static::createLogger(),
            static::getOneclickConfig()
        );
    }

    /**
     * Create and return an instance of the TransactionService.
     *
     * @return TransactionService
     */
    public static function createTransactionService()
    {
        return new TransactionService(
            static::createTransactionRepository()
        );
    }

    /**
     * Create and return an instance of the InscriptionService.
     *
     * @return InscriptionService
     */
    public static function createInscriptionService()
    {
        return new InscriptionService(
            static::createInscriptionRepository()
        );
    }

}


