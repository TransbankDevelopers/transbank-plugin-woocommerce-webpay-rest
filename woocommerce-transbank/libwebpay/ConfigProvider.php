<?php

require_once('../../../../wp-load.php');

use Transbank\Webpay\Options;

class ConfigProvider extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'transbank';
    }

    public function getConfig($option) {
        $value = $this->get_option($option);
        if (!empty($value)) {
            return $value;
        }
        $config = Options::defaultConfig();
        switch ($option) {
            case 'webpay_test_mode':
                return $config::DEFAULT_INTEGRATION_TYPE;
            case 'webpay_commerce_code':
                return $config->getCommerceCode();
            case 'webpay_api_key':
                return $config->getApiKey();

        }
        return null;
    }
}
