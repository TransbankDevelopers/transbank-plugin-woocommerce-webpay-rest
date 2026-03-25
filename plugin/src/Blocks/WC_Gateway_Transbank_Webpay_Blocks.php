<?php

namespace Transbank\WooCommerce\WebpayRest\Blocks;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WCGatewayTransbankWebpayBlocks extends AbstractPaymentMethodType
{

    use WCGatewayTransbankBlocks;

    protected $name = 'transbank_webpay_plus_rest';

    public function __construct()
    {
        $this->scriptInfo = require_once $this->getFrontAssetBuildPath() . 'webpay_blocks.asset.php';
        $this->paymentId = $this->name;
        $this->productName = 'webpay';
    }
}
