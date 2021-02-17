<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

class WordpressPluginVersion
{
    protected function includeWordpressPluginFunctions()
    {
        // Mandar ping a cumbre
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH.'wp-admin/includes/plugin.php';
        }
    }

    public function get()
    {
        $this->includeWordpressPluginFunctions();
        $pluginData = get_plugin_data(__DIR__.'/../../webpay-rest.php');

        return $pluginData['Version'];
    }
}
