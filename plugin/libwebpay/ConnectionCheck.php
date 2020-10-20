<?php

use Transbank\WooCommerce\WebpayRest\Helpers\HealthCheck;

class ConnectionCheck {
    public static function check()
    {

        require_once(__DIR__  . '/ConfigProvider.php');

        $configProvider = new ConfigProvider();
        $config = array(
            'MODO' => $configProvider->getConfig('webpay_rest_test_mode'),
            'COMMERCE_CODE' => $configProvider->getConfig('webpay_rest_commerce_code'),
            'API_KEY' => $configProvider->getConfig('webpay_api_key'),
            'ECOMMERCE' => 'woocommerce'
        );
        $healthcheck = new HealthCheck($config);

        $resp = $healthcheck->setCreateTransaction();

        header('Content-Type: application/json');
        ob_clean();
        echo json_encode($resp);
        wp_die();

    }
}
