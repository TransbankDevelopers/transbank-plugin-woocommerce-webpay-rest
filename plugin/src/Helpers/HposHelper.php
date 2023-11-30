<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;
use WC_Order;

class HposHelper
{
    private $isHposAvailable;
    private const WC_VERSION_SINCE_HPOS = '8.2';
    private $wooCommerceVersion;

    public function __construct()
    {
        $this->wooCommerceVersion = get_option('woocommerce_version');
        $this->isHposAvailable = $this->checkIfHposExists();
    }
    public function checkIfHposExists()
    {
        return version_compare( $this->wooCommerceVersion, HposHelper::WC_VERSION_SINCE_HPOS, '>=');
    }

    public function updateMeta(WC_Order $wooCommerceOrder, $key, $value)
    {
        if($this->isHposAvailable) {
            $wooCommerceOrder->update_meta_data($key, $value);
        }
        else {
            update_post_meta($wooCommerceOrder->get_id(), $key, $value);
        }
    }

}
