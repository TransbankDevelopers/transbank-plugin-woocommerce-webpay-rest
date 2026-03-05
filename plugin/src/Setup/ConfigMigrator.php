<?php

namespace Transbank\WooCommerce\WebpayRest\Setup;

use Transbank\WooCommerce\WebpayRest\Config\TransbankGatewayIds;
use Transbank\WooCommerce\WebpayRest\Config\TransbankGatewaySettings;

final class ConfigMigrator
{
    public const OPTION_VERSION = 'transbank_webpay_settings_schema_version';
    public const CURRENT_VERSION = 1;

    public static function maybeMigrate(): void
    {
        $current = (int) get_option(self::OPTION_VERSION, 0);

        if ($current >= self::CURRENT_VERSION) {
            return;
        }

        self::migrateGateway(TransbankGatewayIds::WEBPAY_PLUS_REST);
        self::migrateGateway(TransbankGatewayIds::ONECLICK_MALL_REST);

        update_option(self::OPTION_VERSION, self::CURRENT_VERSION);
    }

    private static function migrateGateway(string $gatewayId): void
    {
        $optionName = sprintf('woocommerce_%s_settings', $gatewayId);
        $raw = get_option($optionName, []);

        if (!is_array($raw) || $raw === []) {
            return;
        }

        $canonical = self::mapLegacyToCanonical($raw);

        if ($canonical === null) {
            return;
        }

        update_option($optionName, $canonical);
    }

    private static function mapLegacyToCanonical(array $raw): ?array
    {
        $changed = false;

        $map = [
            'webpay_rest_environment' => TransbankGatewaySettings::ENVIRONMENT,
            'webpay_rest_commerce_code' => TransbankGatewaySettings::COMMERCE_CODE,
            'webpay_rest_api_key' => TransbankGatewaySettings::API_KEY,
            'webpay_rest_after_payment_order_status' => TransbankGatewaySettings::AFTER_PAYMENT_ORDER_STATUS,
            'webpay_rest_payment_gateway_description' => TransbankGatewaySettings::DESCRIPTION,
            'oneclick_after_payment_order_status' => TransbankGatewaySettings::AFTER_PAYMENT_ORDER_STATUS,
            'oneclick_payment_gateway_description' => TransbankGatewaySettings::DESCRIPTION,
        ];

        foreach ($map as $legacyKey => $canonicalKey) {
            if (isset($raw[$legacyKey]) && !isset($raw[$canonicalKey])) {
                $raw[$canonicalKey] = $raw[$legacyKey];
                $changed = true;
            }
        }

        if (!$changed) {
            return null;
        }

        foreach (array_keys($map) as $legacyKey) {
            unset($raw[$legacyKey]);
        }

        return $raw;
    }
}
