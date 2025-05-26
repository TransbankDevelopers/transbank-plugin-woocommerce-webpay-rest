<?php

namespace Transbank\Plugin\Services;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Transbank\Plugin\Helpers\ErrorUtil;
use Transbank\Plugin\Exceptions\EcommerceException;
use Transbank\Webpay\Options;
use Transbank\Webpay\WebpayPlus\Responses\TransactionCommitResponse;
use Transbank\Webpay\WebpayPlus\Transaction as WebpayPlusTransaction;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionCommitException;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionCreateException;
use Transbank\Plugin\Helpers\ILogger;
use Transbank\Webpay\WebpayPlus;
use Transbank\Plugin\Model\TbkTransaction;
use Transbank\Plugin\Helpers\BuyOrderHelper;
use Transbank\Plugin\Helpers\MaskData;
use Transbank\Plugin\Helpers\TbkConstants;

class WebpayService
{
    const BUY_ORDER_FORMAT = 'wc-{random, length=8}-{orderId}';

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

    /**
     * @var WebpayPlusTransaction
     */
    protected $webpayplusTransaction;

    
    public function __construct(
            $log,
            $config,
        )
    {
        $this->log = $log;
        $this->options = $this->createOptions(
            $config->getEnvironment(),
            $config->getCommerceCode(),
            $config->getApikey());
        $this->webpayplusTransaction = new WebpayPlusTransaction($this->options);
        $this->dataMasker = new MaskData($log, $config->isIntegration());
        $this->buyOrderFormat = BuyOrderHelper::isValidFormat(
            $config->getBuyOrderFormat()) ? $config->getBuyOrderFormat() : self::BUY_ORDER_FORMAT;
    }

    /**
     * @return Options
     */
    private function createOptions($environment, $commerceCode, $apiKey)
    {
        $options = new Options(WebpayPlus::INTEGRATION_API_KEY, WebpayPlus::INTEGRATION_COMMERCE_CODE, Options::ENVIRONMENT_INTEGRATION);
        if ($environment == Options::ENVIRONMENT_PRODUCTION) {
            $options = new Options($apiKey, $commerceCode, Options::ENVIRONMENT_PRODUCTION);
        }
        return $options;
    }

    public function getCommerceCode()
    {
        return $this->options->getCommerceCode();
    }

    public function getEnviroment()
    {
        return $this->options->getIntegrationType();
    }

    /**
     * @param $amount
     * @param $sessionId
     * @param $buyOrder
     * @param $returnUrl
     *
     * @throws EcommerceException
     *
     * @return TbkTransaction
     */
    public function createTransaction($orderId, $amount, $returnUrl)
    {
        try {
            $buyOrder = $this->generateBuyOrder($orderId);
            $randomNumber = uniqid();
            $sessionId = 'wc:sessionId:'.$randomNumber.':'.$orderId;
            $txDate = date('d-m-Y');
            $txTime = date('H:i:s');
            $this->log->logInfo("Creando transacción Webpay Plus. [Datos]:");
            $this->log->logInfo("amount: {$amount} sessionId: {$sessionId} buyOrder: {$buyOrder} returnUrl: {$returnUrl} txDate: {$txDate} txTime: {$txTime}");
            $createResponse = $this->webpayplusTransaction->create($buyOrder, $sessionId, $amount, $returnUrl);
            $this->log->logInfo("Transacción creada. [Respuesta]:");
            $this->log->logInfo(json_encode($createResponse));
            if (isset($createResponse) && isset($createResponse->url) && isset($createResponse->token)) {
                $result = new TbkTransaction();
                $result->setToken($createResponse->token);
                $result->setUrl($createResponse->url);
                $result->setSessionId($sessionId);
                $result->setBuyOrder($buyOrder);
                $result->setAmount($amount);
                $result->setOrderId($orderId);
                $result->setEnvironment($this->options->getIntegrationType());
                $result->setCommerceCode($this->options->getCommerceCode());
                $result->setProduct(TbkConstants::TRANSACTION_WEBPAY_PLUS);
                $result->setStatus(TbkConstants::TRANSACTION_STATUS_INITIALIZED);
                return $result;
            } else {
                $errorMessage = "Error creando la transacción para => buyOrder: {$buyOrder}, amount: {$amount}";
                throw new EcommerceException($errorMessage);
            }
        } catch (TransactionCreateException $e) {
            $errorMessage = "Error creando la transacción para =>
                buyOrder: {$buyOrder}, amount: {$amount}, error: {$e->getMessage()}";
            throw new EcommerceException($errorMessage, $e);
        }
    }

    /**
     * @param $token
     *
     * @throws \Transbank\Plugin\Exceptions\EcommerceException
     *
     * @return \Transbank\Webpay\WebpayPlus\Responses\TransactionCommitResponse
     */
    public function commitTransaction(string $token): TransactionCommitResponse
    {
        try {
            $this->log->logInfo("commitTransaction : token: {$token}");
            if (!isset($token)) {
                throw new EcommerceException('El token webpay es requerido');
            }

            return $this->webpayplusTransaction->commit($token);
        } catch (TransactionCommitException | \InvalidArgumentException | GuzzleException $e) {
            $errorMessage = "Error confirmando la transacción para el token: {$token}, error: {$e->getMessage()}";
            throw new EcommerceException($errorMessage, $e);
        }
    }

    protected function generateBuyOrder($orderId){
        return BuyOrderHelper::generateFromFormat($this->buyOrderFormat, $orderId);
    }

    public function status($token)
    {
        try {
            return $this->webpayplusTransaction->status($token);
        } catch (Exception $e) {
            $errorMessage = ErrorUtil::DEFAULT_STATUS_ERROR_MESSAGE;

            if(ErrorUtil::isMaxTimeError($e)) {
                $errorMessage = ErrorUtil::EXPIRED_TRANSACTION_ERROR_MESSAGE;
            }

            if (ErrorUtil::isApiMismatchError($e)) {
                $errorMessage = ErrorUtil::DEFAULT_STATUS_ERROR_MESSAGE;
            }

            throw new EcommerceException($errorMessage, $token);
        }
    }
}
