<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\WooCommerce\WebpayRest\PaymentGateways\WC_Gateway_Transbank_Oneclick_Mall_REST;
use WC_Gateway_Transbank_Webpay_Plus_REST;

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

    public static function printImage($source = null)
    {
        $trackinfo = static::getInfo();
        if ($source) {
            $trackinfo['source'] = $source;
        } ?>
        <img width="1" height="1" referrerpolicy="origin" style="border-radius: 10px; width: 400px; margin-right: 10px; display: block" src="https://contrata.transbankdevelopers.cl/plugin-info?<?php echo http_build_query($trackinfo); ?>" alt="">
        <?php
    }
}
