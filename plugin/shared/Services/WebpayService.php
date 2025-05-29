<?php

namespace Transbank\Plugin\Services;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Transbank\Plugin\Helpers\ErrorUtil;
use Transbank\Plugin\Exceptions\Webpay\StatusWebpayException;
use Transbank\Plugin\Exceptions\Webpay\RefundWebpayException;
use Transbank\Plugin\Exceptions\Webpay\CommitWebpayException;
use Transbank\Plugin\Exceptions\Webpay\CreateWebpayException;
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
        if ($config->getEnvironment() == Options::ENVIRONMENT_PRODUCTION){
            $this->webpayplusTransaction = WebpayPlusTransaction::buildForProduction(
                $config->getApikey(),
                $config->getCommerceCode()
            );
        }
        else {
            $this->webpayplusTransaction = WebpayPlusTransaction::buildForIntegration(
                WebpayPlus::INTEGRATION_API_KEY,
                WebpayPlus::INTEGRATION_COMMERCE_CODE
            );
        }
        $this->options = $this->webpayplusTransaction->getOptions();
        $this->dataMasker = new MaskData($config->isIntegration());
        $this->buyOrderFormat = BuyOrderHelper::isValidFormat(
            $config->getBuyOrderFormat()) ? $config->getBuyOrderFormat() : self::BUY_ORDER_FORMAT;
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
     * @param $orderId
     * @param $amount
     * @param $returnUrl
     *
     * @throws CreateWebpayException
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
                throw new CreateWebpayException($errorMessage);
            }
        } catch (TransactionCreateException $e) {
            $errorMessage = "Error creando la transacción para =>
                buyOrder: {$buyOrder}, amount: {$amount}, error: {$e->getMessage()}";
            throw new CreateWebpayException($errorMessage, $e);
        }
    }

    /**
     * @param $token
     *
     * @throws CommitWebpayException
     *
     * @return \Transbank\Webpay\WebpayPlus\Responses\TransactionCommitResponse
     */
    public function commitTransaction(string $token): TransactionCommitResponse
    {
        try {
            $this->log->logInfo("commitTransaction : token: {$token}");
            if (!isset($token)) {
                throw new CommitWebpayException('El token webpay es requerido', $token);
            }
            return $this->webpayplusTransaction->commit($token);
        } catch (TransactionCommitException | \InvalidArgumentException | GuzzleException $e) {
            $errorMessage = "Error confirmando la transacción para el token: {$token}, error: {$e->getMessage()}";
            throw new CommitWebpayException($errorMessage, $token, $e);
        }
    }

    protected function generateBuyOrder($orderId){
        return BuyOrderHelper::generateFromFormat($this->buyOrderFormat, $orderId);
    }

    /**
     * @param $token
     *
     * @throws StatusWebpayException
     *
     * @return \Transbank\Webpay\WebpayPlus\Responses\TransactionStatusResponse
     */
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
            throw new StatusWebpayException($errorMessage, $token, $e);
        }
    }

    /**
     * @param $token
     * @param $amount
     *
     * @throws RefundWebpayException
     *
     * @return \Transbank\Webpay\WebpayPlus\Responses\TransactionRefundResponse
     */
    public function refund($token, $amount)
    {
        $errorMessageBase = 'Ocurrió un error al realizar la anulación en Webpay. ';
        $refundInstructions = 'Intente realizar la anulación mediante su portal privado de Transbank.
          Para mayor información del error revise los logs de la transacción.';
        try {
            $response = $this->webpayplusTransaction->refund($token, $amount);
            if (($response->getType() === 'REVERSED' || $response->getType() === 'NULLIFIED') && (int) $response->getResponseCode() === 0) {
                $this->log->logInfo('Rembolso realizado correctamente en Transbank');
                return $response;
            }
            $errorMessage = 'Código de respuesta Transbank: ' . $response->getResponseCode();
            throw new RefundWebpayException($errorMessage, $token);
        } catch (Exception $e) {
            $errorMessage = $errorMessageBase . $e->getMessage() . '. ' . $refundInstructions;
            throw new RefundWebpayException($errorMessage, $token, $e);
        }
    }
}
