<?php

namespace Transbank\WooCommerce\WebpayRest\Blocks;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WCGatewayTransbankWebpayBlocks extends AbstractPaymentMethodType
{

    use WCGatewayTransbankBlocks;

    protected $name = 'transbank_webpay_plus_rest';

    public function __construct()
    {
        $this->productName = 'webpay';
        $this->paymentId = $this->name;
        $this->scriptInfo = require_once $this->getFrontAssetBuildPath() . $this->getFrontEntryBaseName() . '.asset.php';
    }
}
