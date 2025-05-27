<?php

namespace Transbank\WooCommerce\WebpayRest;

use \Exception;
use Transbank\Webpay\Oneclick\MallInscription;
use Transbank\Webpay\Oneclick\MallTransaction;
use Transbank\Webpay\Oneclick;
use Transbank\Webpay\Options;
use Transbank\WooCommerce\WebpayRest\Models\Inscription;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\Plugin\Helpers\MaskData;
use Transbank\Plugin\Exceptions\Oneclick\TimeoutInscriptionOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\UserCancelInscriptionOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\WithoutTokenInscriptionOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\RejectedInscriptionOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\InvalidStatusInscriptionOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\FinishInscriptionOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\GetInscriptionOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\RejectedAuthorizeOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\ConstraintsViolatedAuthorizeOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\CreateTransactionOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\AuthorizeOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\RejectedRefundOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\RefundOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\NotFoundTransactionOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\GetTransactionOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\StatusOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\StartOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\StartInscriptionOneclickException;
use Transbank\Plugin\Helpers\ErrorUtil;
use Transbank\Plugin\Helpers\BuyOrderHelper;
use Transbank\Plugin\Repositories\TransactionRepositoryInterface;
use Transbank\Plugin\Repositories\InscriptionRepositoryInterface;
use Transbank\Plugin\Model\OneclickConfig;

/**
 * Class OneclickTransbankSdk.
 */
class OneclickTransbankSdk extends TransbankSdk
{

    const OPTION_KEY = 'woocommerce_transbank_oneclick_mall_rest_settings';
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
    protected TransactionRepositoryInterface $transactionRepository;
    protected InscriptionRepositoryInterface $inscriptionRepository;
    private $childBuyOrderFormat;

    public function __construct(
        $log,
        OneclickConfig $config,
        $transactionRepository,
        $inscriptionRepository
    ) {
        $this->log = $log;
        $this->options = $this->createOptions(
            $config->getEnvironment(),
            $config->getCommerceCode(),
            $config->getApikey()
        );
        $this->childCommerceCode = $config->getEnvironment() === Options::ENVIRONMENT_PRODUCTION ?
            $config->getChildCommerceCode() : Oneclick::INTEGRATION_CHILD_COMMERCE_CODE_1;
        $this->mallTransaction = new MallTransaction($this->options);
        $this->mallInscription = new MallInscription($this->options);
        $this->dataMasker = new MaskData($config->isIntegration());
        $this->transactionRepository = $transactionRepository;
        $this->inscriptionRepository = $inscriptionRepository;
        $this->buyOrderFormat = BuyOrderHelper::isValidFormat(
            $config->getBuyOrderFormat()
        ) ? $config->getBuyOrderFormat() : self::BUY_ORDER_FORMAT;
        $this->childBuyOrderFormat = BuyOrderHelper::isValidFormat(
            $config->getChildBuyOrderFormat()
        ) ? $config->getChildBuyOrderFormat() : self::CHILD_BUY_ORDER_FORMAT;
    }

    /**
     * @return Options
     */
    private function createOptions($environment, $commerceCode, $apiKey)
    {
        $options = new Options(Oneclick::INTEGRATION_API_KEY, Oneclick::INTEGRATION_COMMERCE_CODE, Options::ENVIRONMENT_INTEGRATION);
        if ($environment == Options::ENVIRONMENT_PRODUCTION) {
            $options = new Options($apiKey, $commerceCode, Options::ENVIRONMENT_PRODUCTION);
        }
        return $options;
    }

    public function getChildCommerceCode()
    {
        return $this->childCommerceCode;
    }

    /* Metodo STATUS  */
    public function status($orderId, $buyOrder)
    {
        try {
            return $this->mallTransaction->status($buyOrder);
        } catch (Exception $e) {
            $errorMessage = ErrorUtil::DEFAULT_STATUS_ERROR_MESSAGE;

            if (ErrorUtil::isMaxTimeError($e)) {
                $errorMessage = ErrorUtil::EXPIRED_TRANSACTION_ERROR_MESSAGE;
            }

            if (ErrorUtil::isApiMismatchError($e)) {
                $errorMessage = ErrorUtil::DEFAULT_STATUS_ERROR_MESSAGE;
            }

            throw new StatusOneclickException($errorMessage, $buyOrder, $e);
        }
    }

    /* Metodo START  */
    public function startInner($orderId, $username, $email, $returnUrl)
    {
        try {
            return $this->mallInscription->start($username, $email, $returnUrl);
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al tratar iniciar la inscripcion: ' . $e->getMessage();
            throw new StartOneclickException($errorMessage, $e);
        }
    }

    /**
     * @param string $orderId
     * @param string   $userId
     * @param string   $email
     * @param string   $returnUrl
     * @param string   $from
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws Transbank\Webpay\Oneclick\Exceptions\InscriptionStartException
     *
     * @return Transbank\Webpay\Oneclick\Responses\InscriptionStartResponse
     */
    public function startInscription($orderId, $userId, $email, $returnUrl, $from)
    {
        global $wpdb;
        $orderId = $orderId !== null ? $orderId : 0;
        $randomNumber = uniqid();
        $username = 'wc:' . $randomNumber . ':' . $userId;

        /*1. Iniciamos la inscripcion */
        $refundResponse = $this->startInner($orderId, $username, $email, $returnUrl);
        $insert = $this->inscriptionRepository->create([
            'token' => $refundResponse->getToken(),
            'username' => $username,
            'order_id' => $orderId,
            'user_id' => $userId,
            'pay_after_inscription' => false,
            'email' => $email,
            'from' => $from,
            'status' => Inscription::STATUS_INITIALIZED,
            'environment' => $this->getEnviroment(),
            'commerce_code' => $this->getCommerceCode(),
        ]);
        /*2. Validamos que la insercion en la bd fue exitosa */
        if (!$insert) {
            $transactionTable = $this->transactionRepository->getTableName();
            $wpdb->show_errors();
            $errorMessage = "La inscripción no se pudo registrar en la tabla: '{$transactionTable}', query: {$wpdb->last_query}, error: {$wpdb->last_error}";
            throw new StartInscriptionOneclickException($errorMessage);
        }
        return $refundResponse;
    }

    /* Metodo FINISH  */
    public function processRequestFromTbkReturn($server, $get, $post)
    {
        $method = $server['REQUEST_METHOD'];
        $params = $method === 'GET' ? $get : $post;
        $tbkToken = isset($params["TBK_TOKEN"]) ? $params['TBK_TOKEN'] : null;
        $tbkSessionId = isset($params["TBK_ID_SESION"]) ? $params['TBK_ID_SESION'] : null;
        $tbkOrdenCompra = isset($params["TBK_ORDEN_COMPRA"]) ? $params['TBK_ORDEN_COMPRA'] : null;

        $this->logOneclickInscriptionRetornandoDesdeTbk($method, $params);

        if (!isset($tbkToken)) {
            $errorMessage = 'No se recibió el token de la inscripción.';
            throw new WithoutTokenInscriptionOneclickException($errorMessage);
        }

        if ($tbkOrdenCompra && $tbkSessionId && !$tbkToken) {
            $errorMessage = 'La inscripción fue cancelada automáticamente por estar inactiva mucho tiempo.';
            $inscription = $this->saveInscriptionWithError(
                $tbkToken,
                'TimeoutInscriptionOneclickException',
                $errorMessage
            );
            throw new TimeoutInscriptionOneclickException($errorMessage, $tbkToken, $inscription);
        }

        if (isset($tbkOrdenCompra)) {
            $errorMessage = 'La inscripción fue anulada por el usuario o hubo un error en el formulario de inscripción.';
            $inscription = $this->saveInscriptionWithError(
                $tbkToken,
                'UserCancelInscriptionOneclickException',
                $errorMessage
            );
            throw new UserCancelInscriptionOneclickException($errorMessage, $tbkToken, $inscription);
        }

        return $tbkToken;
    }

    public function processTbkReturnAndFinishInscription($server, $get, $post)
    {
        $tbkToken = $this->processRequestFromTbkReturn($server, $get, $post);
        $params = [
            'tbkToken' => $tbkToken
        ];
        $inscription = $this->inscriptionRepository->getByToken($tbkToken);

        if ($inscription->status !== Inscription::STATUS_INITIALIZED) {
            $errorMessage = 'La inscripción no se encuentra en estado inicializada: ' . $tbkToken;
            throw new InvalidStatusInscriptionOneclickException(
                $errorMessage,
                $tbkToken,
                $inscription
            );
        }
        $finishInscriptionResponse = $this->finishInscription(
            $inscription->order_id,
            $tbkToken,
            $inscription
        );
        if (!$finishInscriptionResponse->isApproved()) {
            $errorMessage = 'La inscripción de la tarjeta ha sido rechazada (código de respuesta: ' .
                $finishInscriptionResponse->getResponseCode() . ')';
            throw new RejectedInscriptionOneclickException(
                $errorMessage,
                $tbkToken,
                $inscription,
                $finishInscriptionResponse
            );
        }
        return array(
            'inscription' => $inscription,
            'finishInscriptionResponse' => $finishInscriptionResponse
        );
    }

    public function logOneclickInscriptionRetornandoDesdeTbk($method, $params)
    {
        $maskedParams = $this->dataMasker->maskData($params);
        $this->logInfo('Iniciando validación luego de redirección desde tbk => method: ' . $method);
        $this->logInfo(json_encode($maskedParams));
    }

    public function getInscriptionByToken($tbkToken)
    {
        try {
            return $this->inscriptionRepository->getByToken($tbkToken);
        } catch (Exception $e) {
            $error = 'Ocurrió un error al obtener la inscripción: ' . $e->getMessage();
            $this->logError($error);
            throw new GetInscriptionOneclickException($error, $e);
        }
    }

    public function finishInscription($orderId, $tbkToken, $inscription)
    {
        try {
            $response = $this->mallInscription->finish($tbkToken);
            $this->inscriptionRepository->update($inscription->id, [
                'finished' => true,
                'authorization_code' => $response->getAuthorizationCode(),
                'card_type' => $response->getCardType(),
                'card_number' => $response->getCardNumber(),
                'transbank_response' => json_encode($response),
                'status' => $response->isApproved() ? Inscription::STATUS_COMPLETED : Inscription::STATUS_FAILED,
            ]);
            return $response;
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al ejecutar la inscripción: ' . $e->getMessage();
            $ins = $this->saveInscriptionWithError($tbkToken, 'FinishInscriptionOneclickException', $errorMessage);
            throw new FinishInscriptionOneclickException($errorMessage, $tbkToken, $ins, $e);
        }
    }

    public function saveInscriptionWithError($tbkToken, $error, $detailError)
    {
        $inscription = $this->inscriptionRepository->getByToken($tbkToken);
        if ($inscription == null) {
            return null;
        }
        $this->inscriptionRepository->update($inscription->id, [
            'status' => Inscription::STATUS_FAILED,
            'error' => $error,
            'detail_error' => $detailError
        ]);
        return $inscription;
    }


    /* Metodo AUTHORIZE  */
    public function authorizeInner($orderId, $parentBuyOrder, $childBuyOrder, $amount, $username, $token, $txId)
    {
        try {
            $details = [
                [
                    'commerce_code' => $this->getChildCommerceCode(),
                    'buy_order' => $childBuyOrder,
                    'amount' => $amount,
                    'installments_number' => 1,
                ],
            ];
            /*3. Autorizamos el pago*/
            return $this->mallTransaction->authorize(
                $username,
                $token,
                $parentBuyOrder,
                $details
            );
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al ejecutar la autorización: ' . $e->getMessage();
            $this->saveTransactionWithError($txId, 'AuthorizeOneclickException', $errorMessage);
            throw new AuthorizeOneclickException($e->getMessage(), $e);
        }
    }


    public function authorize($orderId, $amount, $username, $token)
    {
        global $wpdb;
        $parentBuyOrder = $this->generateBuyOrder($orderId);
        $childBuyOrder = $this->generateChildBuyOrder($orderId);
        /*1. Creamos la transacción antes de autorizar en TBK */
        $insert = $this->transactionRepository->create([
            'order_id' => $orderId,
            'buy_order' => $parentBuyOrder,
            'child_buy_order' => $childBuyOrder,
            'commerce_code' => $this->getCommerceCode(),
            'child_commerce_code' => $this->getChildCommerceCode(),
            'amount' => $amount,
            'environment' => $this->getEnviroment(),
            'product' => Transaction::PRODUCT_WEBPAY_ONECLICK,
            'status' => Transaction::STATUS_INITIALIZED
        ]);

        /*2. Validamos que la insercion en la bd fue exitosa */
        if (!$insert) {
            $transactionTable = $this->transactionRepository->getTableName();
            $wpdb->show_errors();
            $errorMessage = "La transacción no se pudo registrar en la tabla: '{$transactionTable}', query: {$wpdb->last_query}, error: {$wpdb->last_error}";
            throw new CreateTransactionOneclickException($errorMessage);
        }
        $tx = $this->transactionRepository->getByBuyOrder($parentBuyOrder);
        if (!isset($tx)) {
            $errorMessage = "no se creo la transacción";
            throw new CreateTransactionOneclickException($errorMessage);
        }

        /*3. Autorizamos el pago*/
        $authorizeResponse = $this->authorizeInner($orderId, $parentBuyOrder, $childBuyOrder, $amount, $username, $token, $tx->id);
        $transbankStatus = $authorizeResponse->getDetails()[0]->getStatus() ?? null;

        /*4. Validamos si esta aprobada */
        if (!$authorizeResponse->isApproved()) {
            if ($transbankStatus === 'CONSTRAINTS_VIOLATED') {
                $errorMessage = 'La transacción ha sido rechazada porque se superó el monto máximo por transacción, el monto máximo diario o el número de transacciones diarias configuradas por el comercio para cada usuario';
                $this->saveTransactionWithError($tx->id, 'ConstraintsViolatedAuthorizeOneclickException', $errorMessage);
                throw new ConstraintsViolatedAuthorizeOneclickException($errorMessage, $authorizeResponse);
            } else {
                $errorCode = $authorizeResponse->getDetails()[0]->getResponseCode() ?? null;
                $errorMessage = 'La transacción ha sido rechazada (Código de error: ' . $errorCode . ')';
                $this->saveTransactionWithError($tx->id, 'RejectedAuthorizeOneclickException', $errorMessage);
                throw new RejectedAuthorizeOneclickException($errorMessage, $authorizeResponse);
            }
        }

        $this->transactionRepository->update(
            $tx->id,
            [
                'status' => Transaction::STATUS_APPROVED,
                'transbank_status' => $transbankStatus,
                'transbank_response' => json_encode($authorizeResponse),
            ]
        );

        return $authorizeResponse;
    }

    public function saveTransactionWithError($txId, $error, $detailError)
    {
        $this->transactionRepository->update(
            $txId,
            [
                'status' => Transaction::STATUS_FAILED,
                'error' => $error,
                'detail_error' => $detailError
            ]
        );
    }



    /* Metodo REFUND  */
    public function getTransactionApprovedByOrderId($orderId)
    {
        try {
            return $this->transactionRepository->findFirstApprovedByOrderId($orderId);
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al tratar de obtener la transacción aprobada ("orderId": "' . $orderId . '") desde la base de datos. Error: ' . $e->getMessage();
            $this->logError($errorMessage);
            throw new GetTransactionOneclickException($errorMessage, $orderId, $e);
        }
    }

    public function refundInner($orderId, $buyOrder, $childCommerceCode, $childBuyOrder, $amount, $transaction)
    {
        try {
            return $this->mallTransaction->refund($buyOrder, $childCommerceCode, $childBuyOrder, $amount);
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al ejecutar el refund de la transacción en Webpay ("buyOrder": "' . $buyOrder . '", "childBuyOrder": "' . $childBuyOrder . '", "amount": "' . $amount . '"). Error: ' . $e->getMessage();
            throw new RefundOneclickException($errorMessage, $buyOrder, $childBuyOrder, $transaction, $e);
        }
    }

    public function refundTransaction($orderId, $amount)
    {
        /*1. Extraemos la transacción */
        $tx = $this->getTransactionApprovedByOrderId($orderId);
        if (!$tx) {
            $errorMessage = 'No se encontró una transacción aprobada ("orderId": "' . $orderId . '") en la base de datos';
            throw new NotFoundTransactionOneclickException($errorMessage, $orderId);
        }

        /*2. Realizamos el refund */
        $response = $this->refundInner($orderId, $tx->buy_order, $this->getChildCommerceCode(), $tx->child_buy_order, $amount, $tx);

        /*3. Validamos si fue exitoso */
        if (!(($response->getType() === 'REVERSED' || $response->getType() === 'NULLIFIED') && (int) $response->getResponseCode() === 0)) {
            $errorMessage = 'El refund de la transacción no se pudo realizar en Webpay ("buyOrder": "' . $tx->buy_order . '", "childBuyOrder": "' . $tx->child_buy_order . '", "amount"' . $amount . '". ';
            throw new RejectedRefundOneclickException($errorMessage, $tx->buy_order, $tx->child_buy_order, $tx, $response);
        }
        /*4. Si todo ok guardamos el estado */
        $this->transactionRepository->update(
            $tx->id,
            [
                'last_refund_type' => $response->getType(),
                'last_refund_response' => json_encode($response)
            ]
        );
        return array(
            'transaction' => $tx,
            'refundResponse' => $response
        );
    }

    protected function generateChildBuyOrder($orderId)
    {
        return BuyOrderHelper::generateFromFormat($this->childBuyOrderFormat, $orderId);
    }
}
