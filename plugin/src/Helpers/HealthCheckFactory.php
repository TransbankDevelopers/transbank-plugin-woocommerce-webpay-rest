<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

/**
 * Class HealthCheckFactory.
 */
class HealthCheckFactory
{
    /**
     * @return HealthCheck
     */
    public static function create()
    {
        $configProvider = new ConfigProvider();
        $config = [
            'MODO'          => $configProvider->getConfig('webpay_rest_environment'),
            'COMMERCE_CODE' => $configProvider->getConfig('webpay_rest_commerce_code'),
            'API_KEY'       => $configProvider->getConfig('webpay_api_key'),
            'ECOMMERCE'     => 'woocommerce',
        ];

        return new HealthCheck($config);
    }
}
