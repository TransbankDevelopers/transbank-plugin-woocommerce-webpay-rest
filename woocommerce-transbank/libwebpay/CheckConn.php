<?php
require_once('ConfigProvider.php');
require_once('HealthCheck.php');

$configProvider = new ConfigProvider();
$config = array(
    'MODO' => $configProvider->getConfig('webpay_test_mode'),
    'COMMERCE_CODE' => $configProvider->getConfig('webpay_commerce_code'),
    'API_KEY' => $configProvider->getConfig('webpay_api_key'),
    'ECOMMERCE' => 'woocommerce'
);
$healthcheck = new HealthCheck($config);
$resp = $healthcheck->setCreateTransaction();
echo json_encode($resp);
