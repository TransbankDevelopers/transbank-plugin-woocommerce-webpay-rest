<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\Webpay\WebpayPlus;
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
        $config = WebpayPlus\Transaction::getDefaultOptions();
        switch ($option) {
            case 'webpay_rest_environment':
                return $config->getIntegrationType();
            case 'webpay_rest_commerce_code':
                return $config->getCommerceCode();
            case 'webpay_rest_api_key':
                return $config->getApiKey();

        }

        return null;
    }
}
