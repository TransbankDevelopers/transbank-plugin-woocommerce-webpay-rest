<?php

namespace Transbank\Woocommerce;

use Transbank\WooCommerce\WebpayRest\Helpers\ConfigProvider;
use Transbank\WooCommerce\WebpayRest\Helpers\HealthCheck;
use Transbank\WooCommerce\WebpayRest\Helpers\ReportPdfLog;

class ReportGenerator
{
    public static function download()
    {

        $configProvider = new ConfigProvider();

        $config = [
            'MODO' => $configProvider->getConfig('webpay_rest_environment'),
            'COMMERCE_CODE' => $configProvider->getConfig('webpay_rest_commerce_code'),
            'API_KEY' => $configProvider->getConfig('webpay_rest_api_key'),
            'ECOMMERCE' => 'woocommerce'
        ];


        $document = $_GET["document"];
        $healthcheck = new HealthCheck($config);

        $json = $healthcheck->printFullResume();
        $temp = json_decode($json);
        if ($document == "report"){
            unset($temp->php_info);
        } else {
            $temp = array('php_info' => $temp->php_info);
        }
        $rl = new ReportPdfLog($document);
        $rl->getReport(json_encode($temp));
        wp_die();
    }
}
