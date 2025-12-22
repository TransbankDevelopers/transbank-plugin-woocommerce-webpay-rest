<?php

namespace Transbank\WooCommerce\WebpayRest\Setup;

use Transbank\Webpay\Oneclick;
use Transbank\Webpay\Options;
use Transbank\Webpay\WebpayPlus;
use Transbank\WooCommerce\WebpayRest\Config\TransbankGatewayIds;
use Transbank\WooCommerce\WebpayRest\Config\TransbankGatewaySettings;

final class GatewaySettingsInstaller
{
    public static function installDefaultsIfMissing(): void
    {
        self::installGatewayDefaults(TransbankGatewayIds::WEBPAY_PLUS_REST, self::getWebpayPlusDefaults());
        self::installGatewayDefaults(TransbankGatewayIds::ONECLICK_MALL_REST, self::getOneclickMallDefaults());
    }

    private static function installGatewayDefaults(string $gatewayId, array $defaults): void
    {
        $optionName = sprintf('woocommerce_%s_settings', $gatewayId);
        $existing = get_option($optionName, null);

        if ($existing !== null) {
            return;
        }

        add_option($optionName, $defaults);
    }

    private static function getWebpayPlusDefaults(): array
    {
        return [
            TransbankGatewaySettings::ENABLED => 'no',
            TransbankGatewaySettings::ENVIRONMENT => Options::ENVIRONMENT_INTEGRATION,
            TransbankGatewaySettings::COMMERCE_CODE => WebpayPlus::INTEGRATION_COMMERCE_CODE,
            TransbankGatewaySettings::API_KEY => WebpayPlus::INTEGRATION_API_KEY,
            TransbankGatewaySettings::AFTER_PAYMENT_ORDER_STATUS => '',
            TransbankGatewaySettings::DESCRIPTION => 'Permite el pago de productos y/o servicios, ' .
                'con tarjetas de crédito, débito y prepago a través de Webpay Plus',
            TransbankGatewaySettings::BUY_ORDER_FORMAT => '{orderId}{random, length=8}',
        ];
    }

    private static function getOneclickMallDefaults(): array
    {
        return [
            TransbankGatewaySettings::ENABLED => 'no',
            TransbankGatewaySettings::ENVIRONMENT => Options::ENVIRONMENT_INTEGRATION,
            TransbankGatewaySettings::COMMERCE_CODE => Oneclick::INTEGRATION_COMMERCE_CODE,
            TransbankGatewaySettings::CHILD_COMMERCE_CODE => Oneclick::INTEGRATION_CHILD_COMMERCE_CODE_1,
            TransbankGatewaySettings::API_KEY => Oneclick::INTEGRATION_API_KEY,
            TransbankGatewaySettings::MAX_AMOUNT => 0,
            TransbankGatewaySettings::AFTER_PAYMENT_ORDER_STATUS => '',
            TransbankGatewaySettings::DESCRIPTION => 'Inscribe tu tarjeta de crédito, débito o prepago y luego paga ' .
                'con un solo click a través de Webpay Oneclick',
            TransbankGatewaySettings::BUY_ORDER_FORMAT => 'wc-{random, length=8}-{orderId}',
            TransbankGatewaySettings::CHILD_BUY_ORDER_FORMAT => 'wc-child-{random, length=8}-{orderId}',
        ];
    }
}
