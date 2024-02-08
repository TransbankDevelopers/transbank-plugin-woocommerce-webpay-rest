<?php

namespace Transbank\WooCommerce\WebpayRest;

use \Exception;
use Transbank\Webpay\Oneclick\MallInscription;
use Transbank\Webpay\Oneclick\MallTransaction;
use Transbank\Webpay\Oneclick;
use Transbank\Webpay\Options;
use Transbank\WooCommerce\WebpayRest\Models\Inscription;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\Helpers\MaskData;
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

/**
 * Class OneclickTransbankSdk.
 */
class OneclickTransbankSdk extends TransbankSdk
{

    const OPTION_KEY = 'woocommerce_transbank_oneclick_mall_rest_settings';

    /**
     * @var MallTransaction
     */
    protected $mallTransaction;

    /**
     * @var MallInscription
     */
    protected $mallInscription;

    public function __construct($log, $environment, $commerceCode, $apiKey, $childCommerceCode)
    {
        $this->log = $log;
        $this->options = $this->createOptions($environment, $commerceCode, $apiKey);
        $this->childCommerceCode = $environment === Options::ENVIRONMENT_PRODUCTION ?
            $childCommerceCode : Oneclick::DEFAULT_CHILD_COMMERCE_CODE_1;
        $this->mallTransaction = new MallTransaction($this->options);
        $this->mallInscription = new MallInscription($this->options);
        $this->dataMasker = new MaskData($this->getEnviroment());
    }

    /**
     * @return Options
    */
    private function createOptions($environment, $commerceCode, $apiKey)
    {
        $options = \Transbank\Webpay\Oneclick\MallTransaction::getDefaultOptions();
        if ($environment == Options::ENVIRONMENT_PRODUCTION) {
            $options = Options::forProduction($commerceCode, $apiKey);
        }
        return $options;
    }

    public function getChildCommerceCode()
    {
        return $this->childCommerceCode;
    }

    protected function afterExecutionTbkApi($orderId, $service, $input, $response)
    {
        $maskedInput = $this->dataMasker->maskData($input);
        $maskedResponse = $this->dataMasker->maskData($response);
        $this->logInfo('ORDER_ID: '.$orderId);
        $this->logInfo('INPUT: '.json_encode($maskedInput).' => RESPONSE: '.json_encode($maskedResponse));
        $this->createApiServiceLogBase($orderId, $service, 'webpay_oneclick', $input, $response);
    }

    protected function errorExecutionTbkApi($orderId, $service, $input, $error, $originalError, $customError)
    {
        $this->logErrorWithOrderId($orderId, $service, $input, $error, $originalError, $customError);
        $this->createErrorApiServiceLogBase(
            $orderId,
            $service,
            'webpay_oneclick',
            $input,
            $error,
            $originalError,
            $customError
        );
    }

    protected function errorExecution($orderId, $service, $data, $error, $originalError, $customError)
    {
        $this->logErrorWithOrderId($orderId, $service, $data, $error, $originalError, $customError);
        $this->createTransbankExecutionErrorLogBase(
            $orderId,
            $service,
            'webpay_oneclick',
            $data,
            $error,
            $originalError,
            $customError
        );
    }

    /* Metodo STATUS  */
    public function status($orderId, $buyOrder)
    {
        $params = ['buyOrder'  => $buyOrder];
        try {
            $response = $this->mallTransaction->status($buyOrder);
            $this->afterExecutionTbkApi($orderId, 'status', $params, $response);
            return $response;
        } catch (Exception $e) {
            $maskedBuyOrder = $this->dataMasker->maskBuyOrder($buyOrder);
            $errorMessage = 'Oneclick: Error al obtener el status ( buyOrder: '.$maskedBuyOrder.') '.$e->getMessage();
            $this->errorExecutionTbkApi($orderId,
                                        'status',
                                        $params,
                                        'StatusOneclickException',
                                        $e->getMessage(),
                                        $errorMessage);
            throw new StatusOneclickException($errorMessage, $buyOrder, $e);
        }
    }

    /* Metodo START  */
    public function startInner($orderId, $username, $email, $returnUrl)
    {
        $params = [
            'username'  => $username,
            'email'     => $email,
            'returnUrl' => $returnUrl
        ];
        try {
            $response = $this->mallInscription->start($username, $email, $returnUrl);
            $this->afterExecutionTbkApi($orderId, 'start', $params, $response);
            return $response;
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al tratar iniciar la inscripcion: '.$e->getMessage();
            $this->errorExecutionTbkApi($orderId, 'start', $params, 'StartOneclickException', $e->getMessage(), $errorMessage);
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
        $username = 'wc:'.$randomNumber.':'.$userId;
        $params = [
            'username'  => $username,
            'email'     => $email,
            'returnUrl' => $returnUrl,
            'from'      => $from
        ];

        /*1. Iniciamos la inscripcion */
        $refundResponse = $this->startInner($orderId, $username, $email, $returnUrl);
        $insert = Inscription::create([
            'token'                 => $refundResponse->getToken(),
            'username'              => $username,
            'order_id'              => $orderId,
            'user_id'               => $userId,
            'pay_after_inscription' => false,
            'email'                 => $email,
            'from'                  => $from,
            'status'                => Inscription::STATUS_INITIALIZED,
            'environment'           => $this->getEnviroment(),
            'commerce_code'         => $this->getCommerceCode(),
        ]);
        /*2. Validamos que la insercion en la bd fue exitosa */
        if (!$insert) {
            $transactionTable = Transaction::getTableName();
            $wpdb->show_errors();
            $errorMessage = "La inscripción no se pudo registrar en la tabla: '{$transactionTable}', query: {$wpdb->last_query}, error: {$wpdb->last_error}";
            $this->errorExecution($orderId, 'start', $params, 'StartInscriptionOneclickException', $wpdb->last_error, $errorMessage);
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

        $params1 = [
            'method' => $method,
            'params' => $params
        ];

        $this->logOneclickInscriptionRetornandoDesdeTbk($method, $params);

        if (!isset($tbkToken)) {
            $errorMessage = 'No se recibió el token de la inscripción.';
            $this->errorExecution(0, 'finish', $params1, 'WithoutTokenInscriptionOneclickException', $errorMessage, $errorMessage);
            throw new WithoutTokenInscriptionOneclickException($errorMessage);
        }

        if ($tbkOrdenCompra && $tbkSessionId && !$tbkToken) {
            $errorMessage = 'La inscripción fue cancelada automáticamente por estar inactiva mucho tiempo.';
            $this->errorExecution(0, 'finish', $params1, 'TimeoutInscriptionOneclickException', $errorMessage, $errorMessage);
            $inscription = $this->saveInscriptionWithError($tbkToken,
                'TimeoutInscriptionOneclickException', $errorMessage);
            throw new TimeoutInscriptionOneclickException($errorMessage, $tbkToken, $inscription);
        }

        if (isset($tbkOrdenCompra)) {
            $errorMessage = 'La inscripción fue anulada por el usuario o hubo un error en el formulario de inscripción.';
            $this->errorExecution(0, 'finish', $params1, 'UserCancelInscriptionOneclickException', $errorMessage, $errorMessage);
            $inscription = $this->saveInscriptionWithError($tbkToken,
                'UserCancelInscriptionOneclickException', $errorMessage);
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
        $inscription = Inscription::getByToken($tbkToken);

        if ($inscription->status !== Inscription::STATUS_INITIALIZED) {
            $errorMessage = 'La inscripción no se encuentra en estado inicializada: '.$tbkToken;
            $this->errorExecution($inscription->order_id, 'finish',
                $params, 'InvalidStatusInscriptionOneclickException', $errorMessage, $errorMessage);
            throw new InvalidStatusInscriptionOneclickException($errorMessage,
                $tbkToken, $inscription);
        }
        $finishInscriptionResponse = $this->finishInscription($inscription->order_id,
            $tbkToken, $inscription);
        if (!$finishInscriptionResponse->isApproved()) {
            $errorMessage = 'La inscripción de la tarjeta ha sido rechazada (código de respuesta: '.
                $finishInscriptionResponse->getResponseCode().')';
            $this->errorExecution($inscription->order_id, 'finish', $params,
                'RejectedInscriptionOneclickException', $errorMessage, $errorMessage);
            throw new RejectedInscriptionOneclickException($errorMessage,
                $tbkToken, $inscription, $finishInscriptionResponse);
        }
        return array(
            'inscription' => $inscription,
            'finishInscriptionResponse' => $finishInscriptionResponse
        );
    }

    public function logOneclickInscriptionRetornandoDesdeTbk($method, $params)
    {
        $maskedParams = $this->dataMasker->maskData($params);
        $this->logInfo('Iniciando validación luego de redirección desde tbk => method: '.$method);
        $this->logInfo(json_encode($maskedParams));
    }

    public function getInscriptionByToken($tbkToken)
    {
        try {
            return Inscription::getByToken($tbkToken);
        } catch (Exception $e) {
            $error = 'Ocurrió un error al obtener la inscripción: '.$e->getMessage();
            $this->logError($error);
            throw new GetInscriptionOneclickException($error, $e);
        }
    }

    public function finishInscription($orderId, $tbkToken, $inscription)
    {
        $params = ['tbkToken'  => $tbkToken];
        try {
            $response = $this->mallInscription->finish($tbkToken);
            $this->afterExecutionTbkApi($orderId, 'finish', $params, $response);
            Inscription::update($inscription->id, [
                'finished'           => true,
                'authorization_code' => $response->getAuthorizationCode(),
                'card_type'          => $response->getCardType(),
                'card_number'        => $response->getCardNumber(),
                'transbank_response' => json_encode($response),
                'status'             => $response->isApproved() ? Inscription::STATUS_COMPLETED : Inscription::STATUS_FAILED,
            ]);
            return $response;
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al ejecutar la inscripción: '.$e->getMessage();
            $this->errorExecutionTbkApi($orderId, 'finish', $params, 'FinishInscriptionOneclickException', $e->getMessage(), $errorMessage);
            $ins = $this->saveInscriptionWithError($tbkToken, 'FinishInscriptionOneclickException', $errorMessage);
            throw new FinishInscriptionOneclickException($errorMessage, $tbkToken, $ins, $e);
        }
    }

    public function saveInscriptionWithError($tbkToken, $error, $detailError)
    {
        $inscription = Inscription::getByToken($tbkToken);
        if ($inscription == null) {
            return null;
        }
        Inscription::update($inscription->id, [
            'status' => Inscription::STATUS_FAILED,
            'error' => $error,
            'detail_error' => $detailError
        ]);
        return $inscription;
    }


    /* Metodo AUTHORIZE  */
    public function authorizeInner($orderId, $parentBuyOrder, $childBuyOrder, $amount, $username, $token, $txId)
    {
        $params = [
            'child_commerce_code'    => $this->getChildCommerceCode(),
            'parentBuyOrder'         => $parentBuyOrder,
            'childBuyOrder'          => $childBuyOrder,
            'amount'                 => $amount,
            'username'               => $username,
            'token'                  => $token
        ];
        try {
            $details = [
                [
                    'commerce_code'       => $this->getChildCommerceCode(),
                    'buy_order'           => $childBuyOrder,
                    'amount'              => $amount,
                    'installments_number' => 1,
                ],
            ];
            /*3. Autorizamos el pago*/
            $response = $this->mallTransaction->authorize(
                $username,
                $token,
                $parentBuyOrder,
                $details
            );
            $this->afterExecutionTbkApi($orderId, 'authorize', $params, $response);
            return $response;
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al ejecutar la autorización: '.$e->getMessage();
            $this->errorExecutionTbkApi($orderId, 'authorize', $params, 'AuthorizeOneclickException', $e->getMessage(), $errorMessage);
            $this->saveTransactionWithError($txId, 'AuthorizeOneclickException', $errorMessage);
            throw new AuthorizeOneclickException($e->getMessage(), $e);
        }
    }


    public function authorize($orderId, $amount, $username, $token) {
        global $wpdb;
        $parentBuyOrder = $this->generateBuyOrder('wc:', $orderId);
        $childBuyOrder = $this->generateBuyOrder('wc:child:', $orderId);
        $params = [
            'orderId'           => $orderId,
            'buyOrder'          => $parentBuyOrder,
            'childBuyOrder'     => $childBuyOrder,
            'childCommerceCode' => $this->getChildCommerceCode(),
            'amount'            => $amount,
            'username'          => $username,
            'token'             => $token
        ];

        /*1. Creamos la transacción antes de autorizar en TBK */
        $insert = Transaction::createTransaction([
            'order_id'            => $orderId,
            'buy_order'           => $parentBuyOrder,
            'child_buy_order'     => $childBuyOrder,
            'commerce_code'       => $this->getCommerceCode(),
            'child_commerce_code' => $this->getChildCommerceCode(),
            'amount'              => $amount,
            'environment'         => $this->getEnviroment(),
            'product'             => Transaction::PRODUCT_WEBPAY_ONECLICK,
            'status'              => Transaction::STATUS_INITIALIZED
        ]);

        /*2. Validamos que la insercion en la bd fue exitosa */
        if (!$insert) {
            $transactionTable = Transaction::getTableName();
            $wpdb->show_errors();
            $errorMessage = "La transacción no se pudo registrar en la tabla: '{$transactionTable}', query: {$wpdb->last_query}, error: {$wpdb->last_error}";
            $this->errorExecution($orderId, 'authorize', $params, 'CreateTransactionOneclickException', $wpdb->last_error, $errorMessage);
            throw new CreateTransactionOneclickException($errorMessage);
        }
        $tx = Transaction::getByBuyOrder($parentBuyOrder);
        if (!isset($tx)) {
            $errorMessage = "no se creo la transacción";
            $this->errorExecution($orderId, 'authorize', $params, 'CreateTransactionOneclickException', $errorMessage, $errorMessage);
            throw new CreateTransactionOneclickException($errorMessage);
        }

        /*3. Autorizamos el pago*/
        $authorizeResponse = $this->authorizeInner($orderId, $parentBuyOrder, $childBuyOrder, $amount, $username, $token, $tx->id);
        $transbankStatus = $authorizeResponse->getDetails()[0]->getStatus() ?? null;

        /*4. Validamos si esta aprobada */
        if (!$authorizeResponse->isApproved()) {
            if ($transbankStatus === 'CONSTRAINTS_VIOLATED') {
                $errorMessage = 'La transacción ha sido rechazada porque se superó el monto máximo por transacción, el monto máximo diario o el número de transacciones diarias configuradas por el comercio para cada usuario';
                $this->errorExecution($orderId, 'authorize', $params, 'ConstraintsViolatedAuthorizeOneclickException', $errorMessage, $errorMessage);
                $this->saveTransactionWithError($tx->id, 'ConstraintsViolatedAuthorizeOneclickException', $errorMessage);
                throw new ConstraintsViolatedAuthorizeOneclickException($errorMessage, $authorizeResponse);
            } else {
                $errorCode = $authorizeResponse->getDetails()[0]->getResponseCode() ?? null;
                $errorMessage = 'La transacción ha sido rechazada (Código de error: '.$errorCode.')';
                $this->errorExecution($orderId, 'authorize', $params, 'RejectedAuthorizeOneclickException', $errorMessage, $errorMessage);
                $this->saveTransactionWithError($tx->id, 'RejectedAuthorizeOneclickException', $errorMessage);
                throw new RejectedAuthorizeOneclickException($errorMessage, $authorizeResponse);
            }
        }

        Transaction::update(
            $tx->id,
            [
                'status'              => Transaction::STATUS_APPROVED,
                'transbank_status'    => $transbankStatus,
                'transbank_response'  => json_encode($authorizeResponse),
            ]
        );

        return $authorizeResponse;
    }

    public function saveTransactionWithError($txId, $error, $detailError)
    {
        Transaction::update(
            $txId,
            [
                'status'        => Transaction::STATUS_FAILED,
                'error'         => $error,
                'detail_error'  => $detailError
            ]
        );
    }



    /* Metodo REFUND  */
    public function getTransactionApprovedByOrderId($orderId)
    {
        try {
            return Transaction::getApprovedByOrderId($orderId);
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al tratar de obtener la transacción aprobada ("orderId": "'.$orderId.'") desde la base de datos. Error: '.$e->getMessage();
            $this->logError($errorMessage);
            throw new GetTransactionOneclickException($errorMessage, $orderId, $e);
        }
    }

    public function refundInner($orderId, $buyOrder, $childCommerceCode, $childBuyOrder, $amount, $transaction)
    {
        $params = [
            'child_commerce_code'    => $childCommerceCode,
            'buyOrder'               => $buyOrder,
            'childBuyOrder'          => $childBuyOrder,
            'amount'                 => $amount
        ];
        try {
            $response = $this->mallTransaction->refund($buyOrder, $childCommerceCode, $childBuyOrder, $amount);
            $this->afterExecutionTbkApi($orderId, 'refund', $params, $response);
            return $response;
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al ejecutar el refund de la transacción en Webpay ("buyOrder": "'.$buyOrder.'", "childBuyOrder": "'.$childBuyOrder.'", "amount": "'.$amount.'"). Error: '.$e->getMessage();
            $this->errorExecutionTbkApi($orderId, 'refund', $params, 'RefundOneclickException', $e->getMessage(), $errorMessage);
            throw new RefundOneclickException($errorMessage, $buyOrder, $childBuyOrder, $transaction, $e);
        }
    }

    public function refundTransaction($orderId, $amount)
    {
        $params = [
            'orderId'    => $orderId,
            'amount'     => $amount
        ];
        /*1. Extraemos la transacción */
        $tx = $this->getTransactionApprovedByOrderId($orderId);
        if (!$tx) {
            $errorMessage = 'No se encontró una transacción aprobada ("orderId": "'.$orderId.'") en la base de datos';
            $this->errorExecution($orderId, 'refund', $params, 'NotFoundTransactionOneclickException', $errorMessage, $errorMessage);
            throw new NotFoundTransactionOneclickException($errorMessage, $orderId);
        }

        /*2. Realizamos el refund */
        $response = $this->refundInner($orderId, $tx->buy_order, $this->getChildCommerceCode(), $tx->child_buy_order, $amount, $tx);

        /*3. Validamos si fue exitoso */
        if (!(($response->getType() === 'REVERSED' || $response->getType() === 'NULLIFIED') && (int) $response->getResponseCode() === 0)) {
            $errorMessage = 'El refund de la transacción no se pudo realizar en Webpay ("buyOrder": "'.$tx->buy_order.'", "childBuyOrder": "'.$tx->child_buy_order.'", "amount"'.$amount.'". ';
            $this->errorExecution($orderId, 'refund', $params, 'RejectedRefundOneclickException', $errorMessage, $errorMessage);
            throw new RejectedRefundOneclickException($errorMessage, $tx->buy_order, $tx->child_buy_order, $tx, $response);
        }
        /*4. Si todo ok guardamos el estado */
        Transaction::update(
            $tx->id,
            [
                'last_refund_type'    => $response->getType(),
                'last_refund_response'   => json_encode($response)
            ]
        );
        return array(
            'transaction' => $tx,
            'refundResponse' => $response
        );
    }
}
