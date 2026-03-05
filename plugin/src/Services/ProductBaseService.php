<?php

namespace Transbank\WooCommerce\WebpayRest\Services;

use Transbank\Webpay\Options;
use Transbank\Plugin\Helpers\BuyOrderHelper;

class ProductBaseService
{
    /**
     * @var Options
     */
    public $options;

    protected $buyOrderFormat;

    public function getCommerceCode()
    {
        return $this->options->getCommerceCode();
    }

    public function getEnvironment()
    {
        return $this->options->getIntegrationType();
    }

    protected function generateBuyOrder($orderId)
    {
        return BuyOrderHelper::generateFromFormat($this->buyOrderFormat, $orderId);
    }

}
