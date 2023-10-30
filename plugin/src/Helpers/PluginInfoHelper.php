<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\WooCommerce\WebpayRest\PaymentGateways\WC_Gateway_Transbank_Oneclick_Mall_REST;
use Transbank\WooCommerce\WebpayRest\PaymentGateways\WC_Gateway_Transbank_Webpay_Plus_REST;

class PluginInfoHelper
{
    public static function getInfo()
    {
        global $transbankPluginData;
        $webpayPlus = new WC_Gateway_Transbank_Webpay_Plus_REST();
        $webpayPlusEnvironment = $webpayPlus->get_option('webpay_rest_environment');
        $webpayPlusCommerceCode = $webpayPlus->get_option('webpay_rest_commerce_code');

        $oneclick = new WC_Gateway_Transbank_Oneclick_Mall_REST();
        $oneclickEnvironment = $oneclick->get_option('environment');
        $oneclickCommerceCode = $oneclick->get_option('commerce_code');
        $oneclickMaxAmount = $oneclick->get_option('max_amount');

        if ($transbankPluginData) {
            $pluginVersion = $transbankPluginData['Version'] ?? '0';
        }

        return [
            'plugin'            => 'wc',
            'version'           => $pluginVersion ?? null,
            'wpcommerce'        => $webpayPlusCommerceCode,
            'wpenv'             => $webpayPlusEnvironment,
            'oneclickenv'       => $oneclickEnvironment,
            'oneclickcommerce'  => $oneclickCommerceCode,
            'oneclickmaxamount' => $oneclickMaxAmount,
        ];
    }
}
