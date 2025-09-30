<?php

namespace Transbank\WooCommerce\WebpayRest\Services;

use Exception;
use Transbank\Webpay\Oneclick;
use Transbank\Webpay\Options;
use Transbank\Plugin\Helpers\MaskData;
use Transbank\Plugin\Helpers\ErrorUtil;
use Transbank\Plugin\Model\TbkTransaction;
use Transbank\Plugin\Helpers\TbkConstants;
use Transbank\Plugin\Helpers\BuyOrderHelper;
use Transbank\Webpay\Oneclick\MallTransaction;
use Transbank\Plugin\Exceptions\Oneclick\AuthorizeOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\RefundOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\StatusOneclickException;

class OneclickAuthorizationService extends ProductBaseService
{
    const BUY_ORDER_FORMAT = 'wc-{random, length=8}-{orderId}';
    const CHILD_BUY_ORDER_FORMAT = 'wc-child-{random, length=8}-{orderId}';

    /**
     * @var MallTransaction
     */
    protected $mallTransaction;
    private $childBuyOrderFormat;
    private $childCommerceCode;

    public function __construct(
        $log,
        $config
    ) {
        $this->log = $log;
        if ($config->getEnvironment() == Options::ENVIRONMENT_PRODUCTION) {
            $this->mallTransaction = MallTransaction::buildForProduction(
                $config->getApikey(),
                $config->getCommerceCode()
            );
            $this->childCommerceCode = $config->getChildCommerceCode();
        } else {
            $this->mallTransaction = MallTransaction::buildForIntegration(
                Oneclick::INTEGRATION_API_KEY,
                Oneclick::INTEGRATION_COMMERCE_CODE
            );
            $this->childCommerceCode = Oneclick::INTEGRATION_CHILD_COMMERCE_CODE_1;
        }
        $this->options = $this->mallTransaction->getOptions();
        $this->dataMasker = new MaskData(isIntegration: $config->isIntegration());
        $this->buyOrderFormat = BuyOrderHelper::isValidFormat(
            $config->getBuyOrderFormat()
        ) ? $config->getBuyOrderFormat() : self::BUY_ORDER_FORMAT;
        $this->childBuyOrderFormat = BuyOrderHelper::isValidFormat(
            $config->getChildBuyOrderFormat()
        ) ? $config->getChildBuyOrderFormat() : self::CHILD_BUY_ORDER_FORMAT;
    }

    /**
     * @param $username
     * @param $tbkUser
     * @param $parentBuyOrder
     * @param $childBuyOrder
     * @param $amount
     *
     * @throws AuthorizeOneclickException
     *
     * @return \Transbank\Webpay\Oneclick\Responses\MallTransactionAuthorizeResponse
     */
    public function authorize($username, $tbkUser, $parentBuyOrder, $childBuyOrder, $amount)
    {
        try {
            $txDate = date('d-m-Y');
            $txTime = date('H:i:s');
            $logMessage = "authorize => username: {$username} parentBuyOrder: {$parentBuyOrder} "
                . "childBuyOrder: {$childBuyOrder}, amount: {$amount}, "
                . "txDate: {$txDate}, txTime: {$txTime}";
            $this->log->logInfo($logMessage);
            $details = [
                [
                    'commerce_code' => $this->getChildCommerceCode(),
                    'buy_order' => $childBuyOrder,
                    'amount' => $amount,
                    'installments_number' => 1,
                ],
            ];
            $resp = $this->mallTransaction->authorize($username, $tbkUser, $parentBuyOrder, $details);
            $this->log->logInfo('authorize - resp: ' . json_encode($resp));
            return $resp;
        } catch (Exception $e) {
            $errorMessage = "Error al autorizar el pago para => userName:
                {$username}, buyOrder: {$parentBuyOrder}, error: {$e->getMessage()}";
            $this->log->logError($errorMessage);
            throw new AuthorizeOneclickException($errorMessage, $e);
        }
    }

    /**
     * @param $buyOrder
     *
     * @throws StatusOneclickException
     *
     * @return \Transbank\Webpay\Oneclick\Responses\MallTransactionStatusResponse
     */
    public function status($buyOrder)
    {
        try {
            return $this->mallTransaction->status($buyOrder);
        } catch (Exception $e) {
            $errorMessage = ErrorUtil::DEFAULT_STATUS_ERROR_MESSAGE;

            if (ErrorUtil::isMaxTimeError($e)) {
                $errorMessage = ErrorUtil::EXPIRED_TRANSACTION_ERROR_MESSAGE;
            }

            if (ErrorUtil::isApiMismatchError($e)) {
                $errorMessage = ErrorUtil::API_MISMATCH_ERROR_MESSAGE;
            }
            throw new StatusOneclickException($errorMessage, $buyOrder, $e);
        }
    }

    /**
     * @param $token
     * @param $amount
     *
     * @throws RefundOneclickException
     *
     * @return \Transbank\Webpay\Oneclick\Responses\MallTransactionRefundResponse
     */
    public function refund($buyOrder, $childCommerceCode, $childBuyOrder, $amount)
    {
        $errorMessageBase = 'Ocurrió un error al realizar la anulación en Webpay. ';
        $refundInstructions = 'Intente realizar la anulación mediante su portal privado de Transbank.
          Para mayor información del error revise los logs de la transacción.';
        try {
            $response = $this->mallTransaction->refund($buyOrder, $childCommerceCode, $childBuyOrder, $amount);
            if (($response->getType() === 'REVERSED' || $response->getType() === 'NULLIFIED') && (int) $response->getResponseCode() === 0) {
                $this->log->logInfo('Rembolso realizado correctamente en Transbank');
                return $response;
            }
            $errorMessage = 'Código de respuesta Transbank: ' . $response->getResponseCode();
            throw new RefundOneclickException($errorMessage, $buyOrder, $childBuyOrder);
        } catch (Exception $e) {
            $errorMessage = $errorMessageBase . $e->getMessage() . '. ' . $refundInstructions;
            throw new RefundOneclickException($errorMessage, $buyOrder, $childBuyOrder, $e);
        }
    }

    public function prepareTransaction($orderId, $amount): TbkTransaction
    {
        $parentBuyOrder = $this->generateBuyOrder($orderId);
        $childBuyOrder = $this->generateChildBuyOrder($orderId);
        $data = new TbkTransaction();
        $data->setBuyOrder($parentBuyOrder);
        $data->setChildBuyOrder($childBuyOrder);
        $data->setAmount($amount);
        $data->setOrderId($orderId);
        $data->setEnvironment($this->getEnvironment());
        $data->setCommerceCode($this->getCommerceCode());
        $data->setChildCommerceCode($this->getChildCommerceCode());
        $data->setProduct(TbkConstants::TRANSACTION_WEBPAY_ONECLICK);
        $data->setStatus(TbkConstants::TRANSACTION_STATUS_INITIALIZED);
        return $data;
    }

    public function getChildCommerceCode()
    {
        return $this->childCommerceCode;
    }

    private function generateChildBuyOrder($orderId)
    {
        return BuyOrderHelper::generateFromFormat($this->childBuyOrderFormat, $orderId);
    }
}
