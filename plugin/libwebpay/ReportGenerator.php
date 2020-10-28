<?php

namespace Transbank\Woocommerce;

use ConfigProvider;
use Transbank\WooCommerce\WebpayRest\Helpers\HealthCheck;
use ReportPdfLog;

class ReportGenerator
{
    public static function download()
    {
        require_once(__DIR__ . '/ConfigProvider.php');
        require_once(__DIR__ . '/ReportPdfLog.php');
        require_once(__DIR__ . '/HealthCheck.php');


        $configProvider = new ConfigProvider();
        $config = array(
            'MODO' => $configProvider->getConfig('webpay_rest_test_mode'),
            'COMMERCE_CODE' => $configProvider->getConfig('webpay_rest_commerce_code'),
            'PUBLIC_CERT' => $configProvider->getConfig('webpay_public_cert'),
            'PRIVATE_KEY' => $configProvider->getConfig('webpay_private_key'),
            'WEBPAY_CERT' => $configProvider->getConfig('webpay_webpay_cert'),
            'ECOMMERCE' => 'woocommerce'
        );

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
