<?php

namespace Transbank\WooCommerce\WebpayRest;

use Transbank\Plugin\Helpers\MaskData;
use Transbank\Plugin\Helpers\BuyOrderHelper;

/**
 * Class TransbankSdk.
 */
class TransbankSdk
{
    /**
    * @var Transbank\Webpay\Options
    */
    public $options;

    protected $childCommerceCode;

    protected $log;
    protected $buyOrderFormat;

    /**
     * @var MaskData
     */
    public $dataMasker;

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
        $maskedBuyOrder = $this->dataMasker->maskBuyOrder($buyOrder);
        $param['environment'] = $this->getEnviroment();
        $param['commerceCode'] = $this->getCommerceCode();
        $maskedParams = $this->dataMasker->maskData($param);
        $this->logInfo('BUY_ORDER: '.$maskedBuyOrder.' => '.$msg.' => data: '.json_encode($maskedParams));
    }

    public function logErrorData($buyOrder, $errorMsg, $param)
    {
        $param['environment'] = $this->getEnviroment();
        $param['commerceCode'] = $this->getCommerceCode();
        $this->logError('BUY_ORDER: '.$buyOrder.' => '.$errorMsg.' => data: '.json_encode($param));
    }

    protected function logErrorWithOrderId($orderId, $service, $input, $error, $originalError, $customError){
        $maskedInput = $this->dataMasker->maskData($input);
        $messageError = (isset($customError) ? $customError : $originalError);
        $this->logError('ORDER_ID: '.$orderId.', SERVICE: '.$service);
        $this->logError('INPUT: '.json_encode($maskedInput).' => EXCEPTION: '.$error.' , ERROR: '.$messageError);
    }

    protected function logInfoWithOrderId($orderId, $service, $message, $data){
        $maskedData = $this->dataMasker->maskData($data);
        $this->logInfo('ORDER_ID: '.$orderId.', SERVICE: '.$service);
        $this->logInfo('message: '.$message.', DATA: '.json_encode($maskedData));
    }

    protected function generateBuyOrder($orderId){
        return BuyOrderHelper::generateFromFormat($this->buyOrderFormat, $orderId);
    }

}
