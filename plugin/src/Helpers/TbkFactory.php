<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\Plugin\Helpers\PluginLogger;
use Transbank\Plugin\Model\LogConfig;
use Transbank\Plugin\Model\WebpayplusConfig;
use Transbank\Plugin\Model\OneclickConfig;
use Transbank\WooCommerce\WebpayRest\Config\TransbankConfig;
use Transbank\WooCommerce\WebpayRest\Repositories\TransactionRepository;
use Transbank\WooCommerce\WebpayRest\Repositories\InscriptionRepository;
use Transbank\WooCommerce\WebpayRest\Repositories\PaymentTokenRepository;
use Transbank\WooCommerce\WebpayRest\Services\EcommerceService;
use Transbank\WooCommerce\WebpayRest\Services\WebpayService;
use Transbank\WooCommerce\WebpayRest\Services\OneclickInscriptionService;
use Transbank\WooCommerce\WebpayRest\Services\OneclickAuthorizationService;
use Transbank\WooCommerce\WebpayRest\Services\TransactionService;
use Transbank\WooCommerce\WebpayRest\Services\InscriptionService;
use Transbank\WooCommerce\WebpayRest\Infrastructure\Database\WpdbTableGateway;
use Transbank\WooCommerce\WebpayRest\Infrastructure\Database\WpdbTableNames;


define(
    'TRANSBANK_WEBPAY_REST_UPLOADS',
    untrailingslashit(wp_upload_dir()['basedir'] . '/transbank_webpay_plus_rest')
);

class TbkFactory
{

    const WEBPAY_OPTION_KEY = 'woocommerce_transbank_webpay_plus_rest_settings';
    const ONECLICK_OPTION_KEY = 'woocommerce_transbank_oneclick_mall_rest_settings';

    public static function createLogger(bool $shouldMask = true)
    {
        $config = new LogConfig(TRANSBANK_WEBPAY_REST_UPLOADS . '/logs', $shouldMask);
        return new PluginLogger($config);
    }

    public static function createOneclickLogger(): PluginLogger
    {
        $shouldMask = !static::getOneclickConfig()->isIntegration();
        return static::createLogger($shouldMask);
    }

    public static function createWebpayPlusLogger(): PluginLogger
    {
        $shouldMask = !static::getWebpayplusConfig()->isIntegration();
        return static::createLogger($shouldMask);
    }

    public static function getWebpayplusConfig(): WebpayplusConfig
    {
        $webpaySettings = TransbankConfig::webpayPlus();

        return new WebpayplusConfig([
            'environment' => $webpaySettings->get($webpaySettings::ENVIRONMENT),
            'commerceCode' => $webpaySettings->get($webpaySettings::COMMERCE_CODE),
            'apikey' => $webpaySettings->get($webpaySettings::API_KEY),
            'buyOrderFormat' => $webpaySettings->get($webpaySettings::BUY_ORDER_FORMAT) ?? WebpayService::BUY_ORDER_FORMAT,
            'statusAfterPayment' => $webpaySettings->get($webpaySettings::AFTER_PAYMENT_ORDER_STATUS) ?? ''
        ]);
    }

    public static function getOneclickConfig(): OneclickConfig
    {
        $oneclickSettings = TransbankConfig::oneclickMall();

        return new OneclickConfig([
            'environment' => $oneclickSettings->get($oneclickSettings::ENVIRONMENT),
            'commerceCode' => $oneclickSettings->get($oneclickSettings::COMMERCE_CODE),
            'apikey' => $oneclickSettings->get($oneclickSettings::API_KEY),
            'childCommerceCode' => $oneclickSettings->get($oneclickSettings::CHILD_COMMERCE_CODE),
            'buyOrderFormat' => $oneclickSettings->get($oneclickSettings::BUY_ORDER_FORMAT) ?? OneclickAuthorizationService::BUY_ORDER_FORMAT,
            'childBuyOrderFormat' => $oneclickSettings->get($oneclickSettings::CHILD_BUY_ORDER_FORMAT) ?? OneclickAuthorizationService::CHILD_BUY_ORDER_FORMAT,
            'statusAfterPayment' => $oneclickSettings->get($oneclickSettings::AFTER_PAYMENT_ORDER_STATUS) ?? ''
        ]);
    }

    /**
     * Create and return an instance of the TransactionRepository.
     *
     * @return TransactionRepository
     */
    public static function createTransactionRepository(): TransactionRepository
    {
        global $wpdb;
        $tableGateway = new WpdbTableGateway(
            $wpdb,
            TransactionRepository::TABLE_NAME,
            ['transbank_response', 'last_refund_response']
        );

        return new TransactionRepository($tableGateway);
    }

    /**
     * Create and return an instance of the InscriptionRepository.
     *
     * @return InscriptionRepository
     */
    public static function createInscriptionRepository(): InscriptionRepository
    {
        global $wpdb;
        $tableGateway = new WpdbTableGateway(
            $wpdb,
            InscriptionRepository::TABLE_NAME,
            ['transbank_response']
        );
        $tableNames = new WpdbTableNames($wpdb);
        return new InscriptionRepository($tableGateway, $tableNames);
    }

    public static function createPaymentTokenRepository(): PaymentTokenRepository
    {
        global $wpdb;
        return new PaymentTokenRepository($wpdb);
    }

    public static function createEcommerceService()
    {
        return new EcommerceService(
            static::getWebpayplusConfig(),
            static::getOneclickConfig()
        );
    }

    public static function createWebpayService()
    {
        return new WebpayService(
            static::getWebpayplusConfig()
        );
    }

    public static function createOneclickInscriptionService()
    {
        return new OneclickInscriptionService(
            static::getOneclickConfig(),
            static::createInscriptionRepository(),
            static::createPaymentTokenRepository()
        );
    }

    public static function createOneclickAuthorizationService()
    {
        return new OneclickAuthorizationService(
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
