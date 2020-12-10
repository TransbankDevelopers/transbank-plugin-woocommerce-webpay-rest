<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\Webpay\Options;
use WC_Payment_Gateway;

class ConfigProvider extends WC_Payment_Gateway
{

    public function __construct()
    {
        $this->id = 'transbank_webpay_plus_rest';
    }

    public function getConfig($option)
    {
        $value = $this->get_option($option);
        if (!empty($value)) {
            return $value;
        }
        $config = Options::defaultConfig();
        switch ($option) {
            case 'webpay_rest_environment':
                return $config::DEFAULT_INTEGRATION_TYPE;
            case 'webpay_rest_commerce_code':
                return $config->getCommerceCode();
            case 'webpay_rest_api_key':
                return $config->getApiKey();

        }

        return null;
    }
}
