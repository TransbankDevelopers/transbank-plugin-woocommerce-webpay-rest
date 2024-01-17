<?php

namespace Transbank\Plugin\Helpers;

use Transbank\Plugin\Exceptions\EcommerceException;

class WoocommerceInfoUtil
{
    public static function getVersion()
    {
        if (!class_exists('WooCommerce')) {
            throw new EcommerceException('No existe instalación WooCommerce');
        }
        if (!defined('WC_VERSION')) {
            throw new EcommerceException('No se puede obtener la versión de WooCommerce');
        }
        return WC_VERSION;
    }

    public static function getPluginVersion()
    {
        $file = __DIR__.'/../../webpay-rest.php';
        $search = ' * Version:';
        $lines = file($file);
        foreach ($lines as $line) {
            if (strpos($line, $search) !== false) {
                return str_replace(' * Version:', '', $line);
            }
        }
        return null;
    }

    /**
     * Este método obtiene un resumen de información del ecommerce Woocommerce
     *
     * @return array
     */
    public static function getSummary()
    {
        $result = [];
        $result['ecommerce'] = TbkConstants::ECOMMERCE_WOOCOMMERCE;
        $result['currentEcommerceVersion'] = WoocommerceInfoUtil::getVersion();
        $result['lastEcommerceVersion'] = GitHubUtil::getLastGitHubReleaseVersion(
            TbkConstants::REPO_OFFICIAL_WOOCOMMERCE);
        $result['currentPluginVersion'] = WoocommerceInfoUtil::getPluginVersion();
        $result['lastPluginVersion'] = GitHubUtil::getLastGitHubReleaseVersion(
            TbkConstants::REPO_WOOCOMMERCE);
        return $result;
    }
}
