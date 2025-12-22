<?php

namespace Transbank\WooCommerce\WebpayRest\Config;

/**
 * Facade for accessing Transbank configuration.
 *
 * Provides a clean, intention-revealing API for retrieving configuration
 * objects without exposing factory or gateway IDs throughout the codebase.
 *
 * Usage examples:
 * - `TransbankConfig::plugin()->isLoggingEnabled()`
 * - `TransbankConfig::webpayPlus()->isEnabled()`
 * - `TransbankConfig::oneclickMall()->get(TransbankGatewaySettings::ENVIRONMENT)`
 *
 * This class should be the preferred entry point for configuration access
 * in application and domain code.
 */
final class TransbankConfig
{
    /**
     * @return TransbankPluginSettings Plugin-wide configuration.
     */
    public static function plugin(): TransbankPluginSettings
    {
        return TransbankConfigFactory::plugin();
    }

    /**
     * @return TransbankGatewaySettings Webpay Plus REST gateway configuration.
     */
    public static function webpayPlus(): TransbankGatewaySettings
    {
        return TransbankConfigFactory::gateway(TransbankGatewayIds::WEBPAY_PLUS_REST);
    }

    /**
     * @return TransbankGatewaySettings Oneclick Mall REST gateway configuration.
     */
    public static function oneclickMall(): TransbankGatewaySettings
    {
        return TransbankConfigFactory::gateway(TransbankGatewayIds::ONECLICK_MALL_REST);
    }
}
