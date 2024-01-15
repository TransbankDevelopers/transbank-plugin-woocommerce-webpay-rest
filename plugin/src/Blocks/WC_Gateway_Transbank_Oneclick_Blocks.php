<?php

namespace Transbank\WooCommerce\WebpayRest\Blocks;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WCGatewayTransbankOneclickBlocks extends AbstractPaymentMethodType {

    use WCGatewayTransbankBlocks;

    protected $name = 'transbank_oneclick_mall_rest';

    public function __construct() {
        $scriptPath = dirname(dirname(plugin_dir_path(__FILE__))) . '/js/front/';
        $this->scriptInfo = require_once $scriptPath . 'oneclick_blocks.asset.php';
        $this->paymentId = $this->name;
        $this->productName = 'oneclick';
    }
}
