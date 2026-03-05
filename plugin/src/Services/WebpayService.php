<?php

namespace Transbank\WooCommerce\WebpayRest\Services;

use GuzzleHttp\Exception\GuzzleException;
use Transbank\Plugin\Exceptions\Webpay\CommitWebpayException;
use Transbank\Plugin\Exceptions\Webpay\CreateWebpayException;
use Transbank\Webpay\Options;
use Transbank\Webpay\WebpayPlus\Responses\TransactionCommitResponse;
use Transbank\Webpay\WebpayPlus\Transaction as WebpayPlusTransaction;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionCommitException;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionCreateException;
use Transbank\Webpay\WebpayPlus;
use Transbank\Plugin\Model\TbkTransaction;
use Transbank\Plugin\Helpers\BuyOrderHelper;
use Transbank\Plugin\Helpers\TbkConstants;

class WebpayService extends ProductBaseService
{
    const BUY_ORDER_FORMAT = 'wc-{random, length=8}-{orderId}';
    /**
     * @var WebpayPlusTransaction
     */
    protected $webpayplusTransaction;


    public function __construct(
        $config,
    ) {
        if ($config->getEnvironment() == Options::ENVIRONMENT_PRODUCTION) {
            $this->webpayplusTransaction = WebpayPlusTransaction::buildForProduction(
                $config->getApikey(),
                $config->getCommerceCode()
            );
        } else {
            $this->webpayplusTransaction = WebpayPlusTransaction::buildForIntegration(
                WebpayPlus::INTEGRATION_API_KEY,
                WebpayPlus::INTEGRATION_COMMERCE_CODE
            );
        }
        $this->options = $this->webpayplusTransaction->getOptions();
        $this->buyOrderFormat = BuyOrderHelper::isValidFormat(
            $config->getBuyOrderFormat()
        ) ? $config->getBuyOrderFormat() : self::BUY_ORDER_FORMAT;
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
            $sessionId = 'wc:sessionId:' . $randomNumber . ':' . $orderId;
            $createResponse = $this->webpayplusTransaction->create($buyOrder, $sessionId, $amount, $returnUrl);
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
            if (!isset($token)) {
                throw new CommitWebpayException('El token webpay es requerido', $token);
            }
            return $this->webpayplusTransaction->commit($token);
        } catch (TransactionCommitException | \InvalidArgumentException | GuzzleException $e) {
            $errorMessage = "Error confirmando la transacción para el token: {$token}, error: {$e->getMessage()}";
            throw new CommitWebpayException($errorMessage, $token, $e);
        }
    }

    /**
     * @param $token
     *
     * @throws \Transbank\Webpay\WebpayPlus\Exceptions\TransactionStatusException
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return \Transbank\Webpay\WebpayPlus\Responses\TransactionStatusResponse
     */
    public function status($token)
    {
        return $this->webpayplusTransaction->status($token);
    }

    /**
     * @param $token
     * @param $amount
     *
     * @throws \Transbank\Webpay\WebpayPlus\Exceptions\TransactionRefundException
     *
     * @return \Transbank\Webpay\WebpayPlus\Responses\TransactionRefundResponse
     */
    public function refund($token, $amount)
    {
            return $this->webpayplusTransaction->refund($token, $amount);
    }
}
