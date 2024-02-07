<?php

namespace Transbank\WooCommerce\WebpayRest;

use Transbank\WooCommerce\WebpayRest\Models\TransbankApiServiceLog;
use Transbank\WooCommerce\WebpayRest\Models\TransbankExecutionErrorLog;
use Transbank\WooCommerce\WebpayRest\Helpers\MaskData;

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

    protected function createApiServiceLogBase($orderId, $service, $product, $input, $response)
    {
        TransbankApiServiceLog::create($orderId, $service, $product, $this->getEnviroment(), $this->getCommerceCode(), json_encode($input), json_encode($response));
    }

    protected function createErrorApiServiceLogBase($orderId, $service, $product, $input, $error, $originalError, $customError)
    {
        TransbankApiServiceLog::createError($orderId, $service, $product, $this->getEnviroment(), $this->getCommerceCode(), json_encode($input), $error, $originalError, $customError);
        $this->createTransbankExecutionErrorLogBase($orderId, $service, $product, $input, $error, $originalError, $customError);
    }

    protected function createTransbankExecutionErrorLogBase($orderId, $service, $product, $data, $error, $originalError, $customError)
    {
        TransbankExecutionErrorLog::create($orderId, $service, $product, $this->getEnviroment(), $this->getCommerceCode(), json_encode($data), $error, $originalError, $customError);
    }

    protected function generateBuyOrder($prefix, $orderId, $maxLength = 25){
        $randomComponentLength = $maxLength - (strlen($prefix) + strlen((string)$orderId));
        $randomComponent = openssl_random_pseudo_bytes(floor($randomComponentLength / 2));
        $randToHexValue = bin2hex($randomComponent);
        return $prefix . $randToHexValue . ":" . $orderId;
    }

}
