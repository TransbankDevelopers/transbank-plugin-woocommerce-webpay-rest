<?php

namespace Transbank\WooCommerce\WebpayRest\Blocks;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WCGatewayTransbankOneclickBlocks extends AbstractPaymentMethodType
{

    use WCGatewayTransbankBlocks;

    protected $name = 'transbank_oneclick_mall_rest';

    public function __construct()
    {
        $this->scriptInfo = require_once $this->getFrontAssetBuildPath() . 'oneclick_blocks.asset.php';
        $this->paymentId = $this->name;
        $this->productName = 'oneclick';
    }
}
