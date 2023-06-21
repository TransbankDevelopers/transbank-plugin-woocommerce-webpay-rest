<?php

namespace Transbank\WooCommerce\WebpayRest;

use Transbank\Webpay\Options;
use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;

/**
 * Class TransbankSdk.
 */
class TransbankSdk
{
    /**
    * @var Options
    */
    public $options;

    protected $childCommerceCode;

    /**
     * @var LogHandler
     */
    protected $log;


    public function logInfo($str)
    {
        $this->log->logInfo($str);
    }

    public function logError($str)
    {
        $this->log->logError($str);
    }

    public function getEnviroment() {
        return $this->options->getIntegrationType();
    }

    public function getCommerceCode() {
        return $this->options->getCommerceCode();
    }

    public function logInfoData($buyOrder, $msg, $param)
    {
        $param['environment'] = $this->getEnviroment();
        $param['commerceCode'] = $this->getCommerceCode();
        $this->logInfo('BUY_ORDER: '.$buyOrder.' => '.$msg.' => data: '.json_encode($param));
    }

    public function logErrorData($buyOrder, $errorMsg, $param)
    {
        $param['environment'] = $this->getEnviroment();
        $param['commerceCode'] = $this->getCommerceCode();
        $this->logError('BUY_ORDER: '.$buyOrder.' => '.$errorMsg.' => data: '.json_encode($param));
    }

  
}

