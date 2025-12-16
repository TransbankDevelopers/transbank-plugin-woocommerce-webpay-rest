<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\Plugin\Helpers\PluginLogger;
use Transbank\Plugin\Model\LogConfig;
use Transbank\Plugin\Model\WebpayplusConfig;
use Transbank\Plugin\Model\OneclickConfig;
use Transbank\Plugin\Repositories\TransactionRepositoryInterface;
use Transbank\Plugin\Repositories\InscriptionRepositoryInterface;
use Transbank\WooCommerce\WebpayRest\Config\TransbankConfig;
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

    public static function createLogger(bool $shouldMask = true)
    {
        $config = new LogConfig(TRANSBANK_WEBPAY_REST_UPLOADS . '/logs', $shouldMask);
        return new PluginLogger($config);
    }

    public static function createOneclickLogger()
    {
        $shouldMask = !static::getOneclickConfig()->isIntegration();
        return static::createLogger($shouldMask);
    }

    public static function createWebpayPlusLogger()
    {
        $shouldMask = !static::getWebpayplusConfig()->isIntegration();
        return static::createLogger($shouldMask);
    }

    public static function getWebpayplusConfig(): WebpayplusConfig
    {
        $webpaySettings = TransbankConfig::webpayPlus();

        return new WebpayplusConfig([
            'environment' => $webpaySettings->get($webpaySettings::OPTION_ENVIRONMENT),
            'commerceCode' => $webpaySettings->get($webpaySettings::OPTION_COMMERCE_CODE),
            'apiKey' => $webpaySettings->get($webpaySettings::OPTION_API_KEY),
            'buyOrderFormat' => $webpaySettings->get($webpaySettings::OPTION_BUY_ORDER_FORMAT) ?? WebpayService::BUY_ORDER_FORMAT,
            'statusAfterPayment' => $webpaySettings->get($webpaySettings::OPTION_AFTER_PAYMENT_ORDER_STATUS) ?? ''
        ]);
    }

    public static function getOneclickConfig(): OneclickConfig
    {
        $oneclickSettings = TransbankConfig::oneclickMall();

        return new OneclickConfig([
            'environment' => $oneclickSettings->get($oneclickSettings::OPTION_ENVIRONMENT),
            'commerceCode' => $oneclickSettings->get($oneclickSettings::OPTION_COMMERCE_CODE),
            'apiKey' => $oneclickSettings->get($oneclickSettings::OPTION_API_KEY),
            'childCommerceCode' => $oneclickSettings->get($oneclickSettings::OPTION_CHILD_COMMERCE_CODE),
            'buyOrderFormat' => $oneclickSettings->get($oneclickSettings::OPTION_BUY_ORDER_FORMAT) ?? OneclickAuthorizationService::BUY_ORDER_FORMAT,
            'childBuyOrderFormat' => $oneclickSettings->get($oneclickSettings::OPTION_CHILD_BUY_ORDER_FORMAT) ?? OneclickAuthorizationService::CHILD_BUY_ORDER_FORMAT,
            'statusAfterPayment' => $oneclickSettings->get($oneclickSettings::OPTION_AFTER_PAYMENT_ORDER_STATUS) ?? ''
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
            static::getOneclickConfig()
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
