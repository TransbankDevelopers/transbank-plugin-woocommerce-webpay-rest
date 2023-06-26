<?php

namespace Transbank\WooCommerce\WebpayRest;

use \Exception;
use Transbank\Webpay\Options;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;
use Transbank\WooCommerce\WebpayRest\Models\TransbankApiServiceLog;
use Transbank\WooCommerce\WebpayRest\Models\TransbankExecutionErrorLog;

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

    protected function logErrorWithOrderId($orderId, $service, $input, $error, $originalError, $customError){
        $messageError = (isset($customError) ? $customError : $originalError);
        $this->logError('ORDER_ID: '.$orderId.', SERVICE: '.$service.', INPUT: '.json_encode($input).' => EXCEPTION: '.$error.' , ERROR: '.$messageError);
    }
    
    protected function createApiServiceLogBase($orderId, $service, $product, $input, $response)
    {
        TransbankApiServiceLog::create($orderId, $service, $product, $this->getEnviroment(), $this->getCommerceCode(), json_encode($input), json_encode($response));
    }

    protected function createErrorApiServiceLogBase($orderId, $service, $product, $input, $error, $originalError, $customError)
    {
        TransbankApiServiceLog::createError($orderId, $service, $product, $this->getEnviroment(), $this->getCommerceCode(), json_encode($input), $error, $originalError, $customError);
    }

    protected function createTransbankExecutionErrorLogBase($orderId, $service, $product, $data, $error, $originalError, $customError)
    {
        TransbankExecutionErrorLog::create($orderId, $service, $product, $this->getEnviroment(), $this->getCommerceCode(), json_encode($data), $error, $originalError, $customError);
    }
  
}

