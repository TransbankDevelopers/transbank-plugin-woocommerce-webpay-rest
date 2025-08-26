<?php

namespace Transbank\WooCommerce\WebpayRest\Services;

use \Exception;
use Transbank\Webpay\Oneclick\MallInscription;
use Transbank\Webpay\Oneclick\MallTransaction;
use Transbank\Webpay\Oneclick;
use Transbank\Webpay\Options;
use Transbank\Plugin\Helpers\MaskData;
use Transbank\Plugin\Exceptions\Oneclick\FinishOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\AuthorizeOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\RefundOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\StatusOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\StartOneclickException;
use Transbank\Plugin\Helpers\ErrorUtil;
use Transbank\Plugin\Helpers\BuyOrderHelper;
use Transbank\Plugin\Model\TbkTransaction;
use Transbank\Plugin\Model\TbkInscription;
use Transbank\Plugin\Helpers\TbkConstants;

class OneclickService extends ProductBaseService
{
    const BUY_ORDER_FORMAT = 'wc-{random, length=8}-{orderId}';
    const CHILD_BUY_ORDER_FORMAT = 'wc-child-{random, length=8}-{orderId}';

    /**
     * @var MallTransaction
     */
    protected $mallTransaction;

    /**
     * @var MallInscription
     */
    protected $mallInscription;
    private $childBuyOrderFormat;
    private $childCommerceCode;

    public function __construct(
        $log,
        $config,
    ) {
        $this->log = $log;
        if ($config->getEnvironment() == Options::ENVIRONMENT_PRODUCTION) {
            $this->mallInscription = MallInscription::buildForProduction(
                $config->getApikey(),
                $config->getCommerceCode()
            );
            $this->mallTransaction = MallTransaction::buildForProduction(
                $config->getApikey(),
                $config->getCommerceCode()
            );
            $this->childCommerceCode = $config->getChildCommerceCode();
        } else {
            $this->mallInscription = MallInscription::buildForIntegration(
                Oneclick::INTEGRATION_API_KEY,
                Oneclick::INTEGRATION_COMMERCE_CODE
            );
            $this->mallTransaction = MallTransaction::buildForIntegration(
                Oneclick::INTEGRATION_API_KEY,
                Oneclick::INTEGRATION_COMMERCE_CODE
            );
            $this->childCommerceCode = Oneclick::INTEGRATION_CHILD_COMMERCE_CODE_1;

        }
        $this->options = $this->mallInscription->getOptions();
        $this->dataMasker = new MaskData(isIntegration: $config->isIntegration());
        $this->buyOrderFormat = BuyOrderHelper::isValidFormat(
            $config->getBuyOrderFormat()
        ) ? $config->getBuyOrderFormat() : self::BUY_ORDER_FORMAT;
        $this->childBuyOrderFormat = BuyOrderHelper::isValidFormat(
            $config->getChildBuyOrderFormat()
        ) ? $config->getChildBuyOrderFormat() : self::CHILD_BUY_ORDER_FORMAT;
    }

    public function getChildCommerceCode()
    {
        return $this->childCommerceCode;
    }

    /**
     * @param $userName
     * @param $email
     * @param $returnUrl
     *
     * @throws StartOneclickException
     *
     * @return \Transbank\Webpay\Oneclick\Responses\InscriptionStartResponse
     */
    public function startInscription($userName, $email, $returnUrl)
    {
        try {
            $txDate = date('d-m-Y');
            $txTime = date('H:i:s');
            $this->log->logInfo('startInscription - userName: ' . $userName . ', email: ' . $email .
                ', txDate: ' . $txDate . ', txTime: ' . $txTime);

            $resp = $this->mallInscription->start($userName, $email, $returnUrl);
            $this->log->logInfo('startInscription - resp: ' . json_encode($resp));
            if (isset($resp) && isset($resp->urlWebpay) && isset($resp->token)) {
                return $resp;
            } else {
                $errorMessage = "Error al iniciar la inscripción para => userName: {$userName}, email: {$email}";
                throw new StartOneclickException($errorMessage);
            }
        } catch (Exception $e) {
            $errorMessage = "Error al iniciar la inscripción para =>
                userName: {$userName}, email: {$email}, error: {$e->getMessage()}";
            $this->log->logError($errorMessage);
            throw new StartOneclickException($errorMessage, $e);
        }
    }

    /**
     * @param $token
     * @param $userName
     * @param $email
     *
     * @throws FinishOneclickException
     *
     * @return \Transbank\Webpay\Oneclick\Responses\InscriptionFinishResponse
     */
    public function finishInscription($token, $userName, $email)
    {
        try {
            $txDate = date('d-m-Y');
            $txTime = date('H:i:s');
            $this->log->logInfo('finish => token: ' . $token . ' userName: ' . $userName . ', email: ' . $email .
                ', txDate: ' . $txDate . ', txTime: ' . $txTime);
            $resp = $this->mallInscription->finish($token);
            $this->log->logInfo('finish - resp: ' . json_encode($resp));
            return $resp;
        } catch (Exception $e) {
            $errorMessage = "Error al confirmar la inscripción para =>
                userName: {$userName}, email: {$email}, error: {$e->getMessage()}";
            $this->log->logError($errorMessage);
            throw new FinishOneclickException($errorMessage, $e);
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
            $this->log->logInfo('authorize => username: ' . $username . ' parentBuyOrder: '
                . $parentBuyOrder . ' childBuyOrder: ' . $childBuyOrder . ', amount: ' . $amount .
                ', txDate: ' . $txDate . ', txTime: ' . $txTime);
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

    private function generateChildBuyOrder($orderId)
    {
        return BuyOrderHelper::generateFromFormat($this->childBuyOrderFormat, $orderId);
    }

    private function generateUsername($userId)
    {
        return 'wc:' . $this->generateRandomId() . ':' . $userId;
    }

    private function generateRandomId($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    public function prepareInscription(
        $userId,
        $userEmail,
        $orderId,
        $from = 'checkout',

    ): TbkInscription {
        $username = $this->generateUsername($userId);
        $data = new TbkInscription();
        $data->setUsername($username);
        $data->setEmail($userEmail);
        $data->setUserId($userId);
        $data->setOrderId($orderId);
        $data->setFrom($from);
        $data->setStatus(TbkConstants::TRANSACTION_STATUS_INITIALIZED);
        $data->setEnvironment($this->getEnviroment());
        $data->setCommerceCode($this->getCommerceCode());
        return $data;
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
        $data->setEnvironment($this->getEnviroment());
        $data->setCommerceCode($this->getCommerceCode());
        $data->setChildCommerceCode($this->getChildCommerceCode());
        $data->setProduct(TbkConstants::TRANSACTION_WEBPAY_ONECLICK);
        $data->setStatus(TbkConstants::TRANSACTION_STATUS_INITIALIZED);
        return $data;
    }

}
