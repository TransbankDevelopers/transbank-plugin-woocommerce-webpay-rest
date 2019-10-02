<?php
require_once('ConfigProvider.php');
require_once('ReportPdfLog.php');
require_once('HealthCheck.php');

$configProvider = new ConfigProvider();
$config = array(
    'MODO' => $configProvider->getConfig('webpay_test_mode'),
    'COMMERCE_CODE' => $configProvider->getConfig('webpay_commerce_code'),
    'API_KEY' => $configProvider->getConfig('webpay_api_key'),
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
