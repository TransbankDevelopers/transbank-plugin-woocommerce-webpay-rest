<?php

namespace Transbank\WooCommerce\WebpayRest\Blocks;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WCGatewayTransbankOneclickBlocks extends AbstractPaymentMethodType
{

    use WCGatewayTransbankBlocks;

    protected $name = 'transbank_oneclick_mall_rest';

    public function __construct()
    {
        $this->productName = 'oneclick';
        $this->paymentId = $this->name;
        $this->scriptInfo = require_once $this->getFrontAssetBuildPath() . $this->getFrontEntryBaseName() . '.asset.php';
    }
}
