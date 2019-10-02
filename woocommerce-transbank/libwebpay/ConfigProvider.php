<?php

require_once('../../../../wp-load.php');

use \Transbank\Webpay\Webpay;
use \Transbank\Webpay\Configuration;

class ConfigProvider extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'transbank';
    }

    public function getConfig($option) {
        $value = $this->get_option($option);
        if (!empty($value)) {
            return $value;
        }
        $config = Configuration::forTestingWebpayPlusNormal();
        switch ($option) {
            case 'webpay_test_mode':
                return Webpay::INTEGRACION;
            case 'webpay_commerce_code':
                return $config->getCommerceCode();
            case 'webpay_public_cert':
                return $config->getPublicCert();
            case 'webpay_private_key':
                return $config->getPrivateKey();
            case 'webpay_webpay_cert':
                return $config->getWebpayCert();
        }
        return null;
    }
}
