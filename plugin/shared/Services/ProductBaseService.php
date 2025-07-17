<?php

namespace Transbank\Plugin\Services;

use Exception;
use Transbank\Webpay\Options;
use Transbank\Plugin\Helpers\ILogger;
use Transbank\Plugin\Helpers\MaskData;
use Transbank\Plugin\Helpers\BuyOrderHelper;

class ProductBaseService
{
    /**
     * @var Options
     */
    public $options;

    /**
     * @var ILogger
     */
    protected $log;
    /**
     * @var MaskData
     */
    public $dataMasker;
    protected $buyOrderFormat;

    public function getCommerceCode()
    {
        return $this->options->getCommerceCode();
    }

    public function getEnviroment()
    {
        return $this->options->getIntegrationType();
    }

    protected function generateBuyOrder($orderId){
        return BuyOrderHelper::generateFromFormat($this->buyOrderFormat, $orderId);
    }

}
