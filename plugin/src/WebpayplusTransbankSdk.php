<?php

namespace Transbank\WooCommerce\WebpayRest;

use \Exception;
use Transbank\Webpay\Options;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\Helpers\ErrorUtil;
use Transbank\WooCommerce\WebpayRest\Helpers\MaskData;
use Transbank\Webpay\WebpayPlus\Transaction as WebpayPlusTransaction;
use Transbank\Plugin\Exceptions\Webpay\AlreadyProcessedException;
use Transbank\Plugin\Exceptions\Webpay\TimeoutWebpayException;
use Transbank\Plugin\Exceptions\Webpay\UserCancelWebpayException;
use Transbank\Plugin\Exceptions\Webpay\DoubleTokenWebpayException;
use Transbank\Plugin\Exceptions\Webpay\CommitWebpayException;
use Transbank\Plugin\Exceptions\Webpay\InvalidStatusWebpayException;
use Transbank\Plugin\Exceptions\Webpay\RejectedCommitWebpayException;
use Transbank\Plugin\Exceptions\Webpay\CreateWebpayException;
use Transbank\Plugin\Exceptions\Webpay\CreateTransactionWebpayException;
use Transbank\Plugin\Exceptions\Webpay\RejectedRefundWebpayException;
use Transbank\Plugin\Exceptions\Webpay\RefundWebpayException;
use Transbank\Plugin\Exceptions\Webpay\NotFoundTransactionWebpayException;
use Transbank\Plugin\Exceptions\Webpay\GetTransactionWebpayException;
use Transbank\Plugin\Exceptions\Webpay\StatusWebpayException;

/**
 * Class WebpayplusTransbankSdk.
 */
class WebpayplusTransbankSdk extends TransbankSdk
{

    const OPTION_KEY = 'woocommerce_transbank_webpay_plus_rest_settings';

    const WEBPAY_NORMAL_FLOW = 'Normal';
    const WEBPAY_TIMEOUT_FLOW = 'Timeout';
    const WEBPAY_ABORTED_FLOW = 'Aborted';
    const WEBPAY_ERROR_FLOW = 'Error';

    const WEBPAY_ALREADY_PROCESSED_MESSAGE = 'La transacción fue procesada anteriormente.';
    const WEBPAY_FAILED_FLOW_MESSAGE = 'Transacción no autorizada.';
    const WEBPAY_TIMEOUT_FLOW_MESSAGE = 'Tiempo excedido en el formulario de Webpay.';
    const WEBPAY_ABORTED_FLOW_MESSAGE = 'Orden anulada por el usuario.';
    const WEBPAY_ERROR_FLOW_MESSAGE = 'Orden cancelada por un error en el formulario de pago.';

    /**
     * @var WebpayPlusTransaction
     */
    protected $webpayplusTransaction;

    public function __construct($log, $environment, $commerceCode, $apiKey)
    {
        $this->log = $log;
        $this->options = $this->createOptions($environment, $commerceCode, $apiKey);
        $this->webpayplusTransaction = new WebpayPlusTransaction($this->options);
        $this->dataMasker = new MaskData($this->getEnviroment());
    }

    /**
     * @return Options
    */
    private function createOptions($environment, $commerceCode, $apiKey)
    {
        $options = WebpayPlusTransaction::getDefaultOptions();
        if ($environment == Options::ENVIRONMENT_PRODUCTION) {
            $options = Options::forProduction($commerceCode, $apiKey);
        }
        return $options;
    }

    protected function afterExecutionTbkApi($orderId, $service, $input, $response)
    {
        $maskedInput = $this->dataMasker->maskData($input);
        $maskedResponse = $this->dataMasker->maskData($response);
        $this->logInfo('ORDER_ID: '.$orderId);
        $this->logInfo('INPUT: '.json_encode($maskedInput).' => RESPONSE: '.json_encode($maskedResponse));
        $this->createApiServiceLogBase($orderId, $service, 'webpay_plus', $input, $response);
    }

    protected function errorExecutionTbkApi($orderId, $service, $input, $error, $originalError, $customError)
    {
        $this->logErrorWithOrderId($orderId, $service, $input, $error, $originalError, $customError);
        $this->createErrorApiServiceLogBase(
            $orderId,
            $service,
            'webpay_plus',
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
            'webpay_plus',
            $data,
            $error,
            $originalError,
            $customError
        );
    }

    /* Metodo STATUS  */
    public function status($orderId, $token)
    {
        $params = ['token'  => $token];
        try {
            $response = $this->webpayplusTransaction->status($token);
            $this->afterExecutionTbkApi($orderId, 'status', $params, $response);
            return $response;
        } catch (Exception $e) {
            if (ErrorUtil::isApiMismatchError($e)) {
                $errorMessage = 'Esta utilizando una version de api distinta a la utilizada para crear la transacción';
                $this->errorExecutionTbkApi($orderId, 'status', $params, 'StatusWebpayException', $e->getMessage(), $errorMessage);
                throw new StatusWebpayException($errorMessage, $token, $e);
            } elseif (ErrorUtil::isMaxTimeError($e)) {
                $errorMessage = 'Ya pasaron mas de 7 dias desde la creacion de la transacción, ya no es posible consultarla por este medio';
                $this->errorExecutionTbkApi($orderId, 'status', $params, 'StatusWebpayException', $e->getMessage(), $errorMessage);
                throw new StatusWebpayException($errorMessage, $token, $e);
            }
            $errorMessage = 'Ocurrió un error al tratar de obtener el status ( token: '.$token.') de la transacción Webpay en Transbank: '.$e->getMessage();
            $this->errorExecutionTbkApi($orderId, 'status', $params, 'StatusWebpayException', $e->getMessage(), $errorMessage);
            throw new StatusWebpayException($errorMessage, $token, $e);
        }
    }

    /* Metodo CREATE  */
    public function createInner($orderId, $buyOrder, $sessionId, $amount, $returnUrl)
    {
        $params = [
            'sessionId'  => $sessionId,
            'amount' => $amount,
            'returnUrl' => $returnUrl
        ];
        try {
            $this->logInfoData($buyOrder, 'Preparando datos antes de crear la transacción en Transbank', $params);
            $response = $this->webpayplusTransaction->create($buyOrder, $sessionId, $amount, $returnUrl);
            $this->afterExecutionTbkApi($orderId, 'create', $params, $response);
            return $response;
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al tratar de crear la transacción en Transbank: '.$e->getMessage();
            $this->errorExecutionTbkApi($orderId, 'create', $params, 'CreateWebpayException', $e->getMessage(), $errorMessage);
            throw new CreateWebpayException($errorMessage, $e);
        }
    }

    public function createTransaction($orderId, $amount, $returnUrl)
    {
        global $wpdb;
        $randomNumber = uniqid();
        $buyOrder = $this->generateBuyOrder('wc:', $orderId);
        $sessionId = 'wc:sessionId:'.$randomNumber.':'.$orderId;
        $params = [
            'sessionId'  => $sessionId,
            'amount' => $amount,
            'returnUrl' => $returnUrl
        ];

        $this->logInfoData($buyOrder, 'Preparando datos antes de crear la transacción en la base de datos', $params);

        /*1. Creamos la transacción antes de crear la tx en TBK */
        $transaction = [
            'order_id'    => $orderId,
            'buy_order'   => $buyOrder,
            'amount'      => $amount,
            'environment'   => $this->getEnviroment(),
            'session_id'  => $sessionId,
            'commerce_code'  => $this->getCommerceCode(),
            'product'     => Transaction::PRODUCT_WEBPAY_PLUS,
            'status'      => Transaction::STATUS_PREPARED,
        ];

        $insert = Transaction::createTransaction($transaction);

        $this->logInfoData($buyOrder, 'Transacción creada en la base de datos con estado "prepared"', $params);

        /*2. Validamos que la insercion en la bd fue exitosa */
        if (!$insert) {
            $transactionTable = Transaction::getTableName();
            $wpdb->show_errors();
            $errorMessage = "La transacción no se pudo registrar en la tabla: '{$transactionTable}', query: {$wpdb->last_query}, error: {$wpdb->last_error}";
            $this->errorExecution($orderId, 'create', $params, 'CreateTransactionWebpayException', $wpdb->last_error, $errorMessage);
            throw new CreateTransactionWebpayException($errorMessage);
        }
        $tx = Transaction::getByBuyOrder($buyOrder);
        if (!isset($tx)) {
            $errorMessage = "No se puede obtener la transacción desde la base de datos";
            $this->errorExecution($orderId, 'create', $params, 'CreateTransactionWebpayException', $errorMessage, $errorMessage);
            throw new CreateTransactionWebpayException($errorMessage);
        }

        /*3. Creamos la transaccion*/
        $createResponse = $this->createInner($orderId, $buyOrder, $sessionId, $amount, $returnUrl);

        /*4. Validamos si esta ok */
        if (!isset($createResponse) || !isset($createResponse->url) || !isset($createResponse->token)) {
            $errorMessage = 'No se pudo crear una transacción válida en Transbank';
            $this->errorExecution($orderId, 'create', $params, 'CreateWebpayException', $errorMessage, $errorMessage);
            throw new CreateWebpayException($errorMessage);
        }
        Transaction::update(
            $tx->id,
            [
                'token'  => $createResponse->token,
                'status' => Transaction::STATUS_INITIALIZED,
            ]
        );
        $this->logInfoData($buyOrder, 'Transacción actualizada en la base de datos con estado "initialized"', $params);

        return $createResponse;
    }

    /* Metodo REFUND  */
    public function getTransactionApprovedByOrderId($orderId)
    {
        try {
            return Transaction::getApprovedByOrderId($orderId);
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al tratar de obtener la transacción aprobada para la "orden": "'.$orderId.'" desde la base de datos. Error: '.$e->getMessage();
            $this->errorExecution($orderId, 'create', [], 'GetTransactionWebpayException', $e->getMessage(), $errorMessage);
            throw new GetTransactionWebpayException($errorMessage, $orderId, $e);
        }
    }

    public function refundInner($orderId, $token, $amount, $tx)
    {
        $params = [
            'token'  => $token,
            'amount' => $amount
        ];
        try {
            $response = $this->webpayplusTransaction->refund($token, $amount);
            $this->afterExecutionTbkApi($orderId, 'refund', $params, $response);
            return $response;
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al ejecutar el refund de la transacción en Webpay con el "token": "'.$token.'" y "monto": "'.$amount.'". Error: '.$e->getMessage();
            $this->errorExecutionTbkApi($orderId, 'refund', $params, 'RefundWebpayException', $e->getMessage(), $errorMessage);
            throw new RefundWebpayException($errorMessage, $token, $tx, $e);
        }
    }

    public function refundTransaction($orderId, $amount)//NotFoundRefundTransactionWebpayException
    {
        $params = [
            'orderId'  => $orderId,
            'amount'  => $amount
        ];
        /*1. Extraemos la transacción */
        $this->logInfoWithOrderId($orderId, 'refund', 'Buscando una transacción aprobada en la bd, que sea válida para ejecutar un refund', $params);
        $tx = $this->getTransactionApprovedByOrderId($orderId);
        if (!$tx) {
            $errorMessage = 'No se encontró una transacción aprobada en la bd, que sea válida para ejecutar un refund para la "orden": "'.$orderId.'"';
            $this->errorExecution($orderId, 'refund', $params, 'NotFoundTransactionWebpayException', $errorMessage, $errorMessage);
            throw new NotFoundTransactionWebpayException($errorMessage, $orderId);
        }

        /*2. Realizamos el refund */
        $this->logInfoWithOrderId($orderId, 'refund', 'Preparando datos antes de hacer refund a la transacción en Transbank', [
            'orderId'  => $orderId,
            'amount'  => $amount,
            'transaction'  => $tx
        ]);
        $refundResponse = $this->refundInner($orderId, $tx->token, $amount, $tx);
        $this->logInfoWithOrderId($orderId, 'refund', 'Se hizo el refund a la transacción en Transbank', [
            'token'  => $tx->token,
            'amount'  => $amount,
            'transaction'  => $tx,
            'response'  => $refundResponse
        ]);

        /*3. Validamos si fue exitoso */
        if (!(($refundResponse->getType() === 'REVERSED' || $refundResponse->getType() === 'NULLIFIED') && (int) $refundResponse->getResponseCode() === 0)) {
            $errorMessage = 'El refund de la transacción ha sido rechazado por Transbank (código de respuesta: "'.$refundResponse->getResponseCode().'")';
            $this->errorExecution($orderId, 'refund', $params, 'RejectedRefundWebpayException', $errorMessage, $errorMessage);
            throw new RejectedRefundWebpayException($errorMessage, $tx->token, $tx, $refundResponse);
        }
        $this->logInfoWithOrderId($orderId, 'refund', '***** REFUND TBK OK *****', [
            'token'  => $tx->token,
            'amount'  => $amount,
        ]);

        /*4. Si todo ok guardamos el estado */
        Transaction::update(
            $tx->id,
            [
                'last_refund_type'    => $refundResponse->getType(),
                'last_refund_response'   => json_encode($refundResponse)
            ]
        );
        return array(
            'transaction' => $tx,
            'refundResponse' => $refundResponse
        );
    }


    /* Metodo COMMIT  */
    public function commitInner($orderId, $token, $transaction)
    {
        $params = ['token'  => $token];
        try {
            $this->logInfoData($transaction->buy_order, 'Preparando datos antes de hacer commit a la transacción en Transbank', $params);
            $response = $this->webpayplusTransaction->commit($token);
            $this->afterExecutionTbkApi($orderId, 'commit', $params, $response);
            return $response;
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al ejecutar el commit de la transacción: '.$e->getMessage();
            $this->errorExecutionTbkApi($orderId, 'commit', $params, 'CommitWebpayException', $e->getMessage(), $errorMessage);
            $this->saveTransactionWithErrorByTransaction($transaction, 'CommitWebpayException', $errorMessage);
            throw new CommitWebpayException($errorMessage, $token, $transaction, $e);
        }
    }

    public function commitTransaction($orderId, $token)
    {
        $params = [
            'token' => $token
        ];
        $transaction = Transaction::getByToken($token);
        if ($transaction->status !== Transaction::STATUS_INITIALIZED) {
            $errorMessage = 'La transacción no se encuentra en estado inicializada: '.$token;
            $this->errorExecution($orderId, 'commit', $params, 'InvalidStatusWebpayException', $errorMessage, $errorMessage);
            throw new InvalidStatusWebpayException($errorMessage, $token, $transaction);
        }
        $commitResponse = $this->commitInner($transaction->order_id, $token, $transaction);
        if (!$commitResponse->isApproved()) {
            $errorMessage = 'El commit de la transacción ha sido rechazada en Transbank (código de respuesta: '.$commitResponse->getResponseCode().')';
            $this->errorExecution($orderId, 'commit', $params, 'RejectedCommitWebpayException', $errorMessage, $errorMessage);
            $this->saveTransactionWithError($transaction->id, 'RejectedCommitWebpayException', $errorMessage, $commitResponse);
            throw new RejectedCommitWebpayException($errorMessage, $token, $transaction, $commitResponse);
        }
        $this->logInfoData($transaction->buy_order, '***** COMMIT TBK OK ***** SI NO SE ENCUENTRA VALIDACION POR WooCommerce DEBE ANULARSE', [
            'token'  => $token,
            'response'  => $commitResponse
        ]);
        Transaction::update(
            $transaction->id,
            [
                'status'             => Transaction::STATUS_APPROVED,
                'transbank_status'   => $commitResponse->getStatus(),
                'transbank_response' => json_encode($commitResponse)
            ]
        );
        return $commitResponse;
    }

    public function saveTransactionWithError($txId, $error, $detailError, $commitResponse = null)
    {
        $data = [
            'status'        => Transaction::STATUS_FAILED,
            'error'         => $error,
            'detail_error'  => $detailError
        ];
        if (isset($commitResponse)) {
            $data['transbank_status'] = $commitResponse->getStatus();
            $data['transbank_response'] = json_encode($commitResponse);
        }
        Transaction::update(
            $txId,
            $data
        );
    }

    public function saveTransactionWithErrorByTransaction($transaction, $error, $detailError)
    {
        if ($transaction->status !== Transaction::STATUS_INITIALIZED) {
            $errorMessage = 'Se quiso guardar la excepción: '.$error.' ('.$detailError.') '.' y la transacción no se encuentra en estado inicializada: '.$transaction->token;
            $this->logError($errorMessage);
        }
        $this->saveTransactionWithError($transaction->id, $error, $detailError);
    }

    public function saveTransactionWithErrorByToken($token, $error, $detailError)
    {
        $transaction = Transaction::getByToken($token);
        $this->saveTransactionWithErrorByTransaction($transaction, $error, $detailError);
        return $transaction;
    }

    private function isTransactionProcessed(string $token): bool
    {
        $transaction = Transaction::getByToken($token);
        $status = $transaction->status;

        return $status != Transaction::STATUS_INITIALIZED;
    }

    private function handleProcessedTransaction(string $token)
    {
        $transaction = get_object_vars(Transaction::getByToken($token)) ?? null;
        $buyOrder = $transaction['buy_order'] ?? null;
        $status = $transaction['status'] ?? null;
        $logMessage = self::WEBPAY_ALREADY_PROCESSED_MESSAGE;

        if ($status == Transaction::STATUS_APPROVED) {
            $this->logInfoData($buyOrder, $logMessage, $transaction);
            throw new AlreadyProcessedException($logMessage, $transaction, self::WEBPAY_NORMAL_FLOW);
        }

        if ($status == Transaction::STATUS_TIMEOUT) {
            $logMessage = self::WEBPAY_TIMEOUT_FLOW_MESSAGE;
            $this->logInfoData($buyOrder, $logMessage, $transaction);
            throw new AlreadyProcessedException($logMessage, $transaction, self::WEBPAY_TIMEOUT_FLOW);
        }

        if ($status == Transaction::STATUS_ABORTED_BY_USER) {
            $logMessage = self::WEBPAY_ABORTED_FLOW_MESSAGE;
            $this->logInfoData($buyOrder, $logMessage, $transaction);
            throw new AlreadyProcessedException($logMessage, $transaction, self::WEBPAY_ABORTED_FLOW);
        }

        if ($status == Transaction::STATUS_FAILED) {
            $logMessage = self::WEBPAY_FAILED_FLOW_MESSAGE;
            $this->logInfoData($buyOrder, $logMessage, $transaction);
            throw new AlreadyProcessedException($logMessage, $transaction, self::WEBPAY_ERROR_FLOW);
        }
    }

    private function detectFlow(array $params): string
    {
        $tokenWs = $params['token_ws'] ?? null;
        $tbkToken = $params['TBK_TOKEN'] ?? null;
        $tbkSessionId = $params['TBK_ID_SESION'] ?? null;

        if ($tokenWs && !$tbkToken && !$tbkSessionId) {
            return self::WEBPAY_NORMAL_FLOW;
        }
        if ($tbkSessionId && !$tbkToken && !$tokenWs) {
            return self::WEBPAY_TIMEOUT_FLOW;
        }
        if ($tbkToken && $tbkSessionId && !$tokenWs) {
            return self::WEBPAY_ABORTED_FLOW;
        }
        return self::WEBPAY_ERROR_FLOW;
    }

    public function handleRequestFromTbkReturn(array $params)
    {
        $tokenWs = $params['token_ws'] ?? null;
        $tbkToken = $params['TBK_TOKEN'] ?? null;
        $sessionId = $params['TBK_ID_SESION'] ?? null;
        $buyOrder = $params['TBK_ORDEN_COMPRA'] ?? null;
        $flow = $this->detectFlow($params);

        if ($flow == self::WEBPAY_NORMAL_FLOW) {
            return $this->handleNormalFlow($tokenWs);
        }
        if ($flow == self::WEBPAY_TIMEOUT_FLOW) {
            return $this->handleTimeoutFlow($sessionId, $buyOrder);
        }
        if ($flow == self::WEBPAY_ABORTED_FLOW) {
            return $this->handleAbortedFlow($tbkToken);
        }
        return $this->handleErrorFlow($tokenWs, $tbkToken);
    }

    private function handleNormalFlow(string $token)
    {
        $this->logInfo("Flujo normal detectado, token: $token");

        if ($this->isTransactionProcessed($token)) {
            return $this->handleProcessedTransaction($token);
        }

        return Transaction::getByToken($token);
    }

    private function handleTimeoutFlow(string $sessionId, string $buyOrder)
    {
        $this->logInfo("Flujo de timeout detectado, sessionId: $sessionId, buyOrder: $buyOrder");
        $transaction = Transaction::getByBuyOrderAndSessionId($buyOrder, $sessionId) ?? null;
        $token = $transaction->token ?? null;

        if ($this->isTransactionProcessed($token)) {
            return $this->handleProcessedTransaction($token);
        }

        $errorMessage = self::WEBPAY_TIMEOUT_FLOW_MESSAGE;
        $orderId = $transaction->order_id ?? 0;
        $data = [
            'TBK_ID_SESION' => $sessionId,
            'TBK_ORDEN_COMPRA' => $buyOrder
        ];
        $this->errorExecution($orderId, 'commit', $data, 'TimeoutWebpayException', $errorMessage, $errorMessage);
        $this->saveTransactionWithErrorByTransaction($transaction, 'TimeoutWebpayException', $errorMessage);
        throw new TimeoutWebpayException($errorMessage, $buyOrder, $sessionId, $transaction);
    }

    private function handleAbortedFlow(string $token)
    {
        $this->logInfo("Flujo de pago abortado detectado, token: $token");

        if ($this->isTransactionProcessed($token)) {
            return $this->handleProcessedTransaction($token);
        }

        $errorMessage = self::WEBPAY_ABORTED_FLOW_MESSAGE;
        $transaction = $this->saveTransactionWithErrorByToken($token, 'UserCancelWebpayException', $errorMessage);
        $this->errorExecution($transaction->order_id, 'commit', $token, 'UserCancelWebpayException', $errorMessage, $errorMessage);
        throw new UserCancelWebpayException($errorMessage, $token, $transaction);
    }

    private function handleErrorFlow(string $token, string $tbkToken)
    {
        $this->logInfo("Flujo con error en el formulario detectado, token: $token");

        if ($this->isTransactionProcessed($token)) {
            return $this->handleProcessedTransaction($token);
        }

        $errorMessage = self::WEBPAY_ERROR_FLOW_MESSAGE;
        $transaction = $this->saveTransactionWithErrorByToken($tbkToken, 'DoubleTokenWebpayException', $errorMessage);
        $this->errorExecution($transaction->order_id, 'commit', $token, 'DoubleTokenWebpayException', $errorMessage, $errorMessage);
        throw new DoubleTokenWebpayException($errorMessage, $tbkToken, $token, $transaction);
    }


}

