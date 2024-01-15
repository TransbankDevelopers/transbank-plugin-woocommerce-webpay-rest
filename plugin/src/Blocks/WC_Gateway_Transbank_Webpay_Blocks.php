<?php

namespace Transbank\WooCommerce\WebpayRest\Blocks;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WCGatewayTransbankWebpayBlocks extends AbstractPaymentMethodType {

    use WCGatewayTransbankBlocks;

    protected $name = 'transbank_webpay_plus_rest';

    public function __construct() {
        $scriptPath = dirname(dirname(plugin_dir_path(__FILE__))) . '/js/front/';
        $this->scriptInfo = require_once $scriptPath . 'webpay_blocks.asset.php';
        $this->paymentId = $this->name;
        $this->productName = 'webpay';
    }
}
