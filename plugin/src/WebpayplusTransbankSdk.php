<?php

namespace Transbank\WooCommerce\WebpayRest;

use \Exception;
use Transbank\Webpay\Options;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;
use Transbank\WooCommerce\WebpayRest\Helpers\ErrorUtil;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\TimeoutWebpayException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\UserCancelWebpayException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\DoubleTokenWebpayException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\CommitWebpayException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\InvalidStatusWebpayException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\RejectedCommitWebpayException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\CreateWebpayException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\CreateTransactionWebpayException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\RejectedRefundWebpayException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\RefundWebpayException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\NotFoundTransactionWebpayException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\GetTransactionWebpayException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\StatusWebpayException;

/**
 * Class WebpayplusTransbankSdk.
 */
class WebpayplusTransbankSdk extends TransbankSdk
{

    /**
     * @var \Transbank\Webpay\WebpayPlus\Transaction
     */
    protected $webpayplusTransaction;

    public function __construct($environment, $commerceCode, $apiKey)
    {
        $this->log = new LogHandler();
        $this->options = $this->createOptions($environment, $commerceCode, $apiKey);
        $this->webpayplusTransaction = new \Transbank\Webpay\WebpayPlus\Transaction($this->options);
    }

    /**
     * @return Options
    */
    private function createOptions($environment, $commerceCode, $apiKey)
    {
        $options = \Transbank\Webpay\WebpayPlus\Transaction::getDefaultOptions();
        if ($environment == 'LIVE') {
            $options = Options::forProduction($commerceCode, $apiKey);
        }
        return $options;
    }

    protected function afterExecutionTbkApi($orderId, $service, $input, $response)
    {
        $this->logInfo('ORDER_ID: '.$orderId.', INPUT: '.json_encode($input).' => RESPONSE: '.json_encode($response));
        $this->createApiServiceLogBase($orderId, $service, 'webpay_plus', $input, $response);
    }

    protected function errorExecutionTbkApi($orderId, $service, $input, $error, $originalError, $customError)
    {
        $this->logError('ORDER_ID: '.$orderId.', INPUT: '.json_encode($input).' => ERROR: '.(isset($customError) ? $customError : $originalError));
        $this->createErrorApiServiceLogBase($orderId, $service, 'webpay_plus', $input, $error, $originalError, $customError);
    }

    protected function errorExecution($orderId, $service, $input, $error, $originalError, $customError)
    {
        $this->logError('ORDER_ID: '.$orderId.', INPUT: '.json_encode($input).' => ERROR: '.(isset($customError) ? $customError : $originalError));
        $this->createTransbankExecutionErrorLogBase($orderId, $service, 'webpay_plus', $input, $error, $originalError, $customError);
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
                throw new StatusWebpayException($errorMessage, $token);
            } elseif (ErrorUtil::isMaxTimeError($e)) {
                $errorMessage = 'Ya pasaron mas de 7 dias desde la creacion de la transacción, ya no es posible consultarla por este medio';
                $this->errorExecutionTbkApi($orderId, 'status', $params, 'StatusWebpayException', $e->getMessage(), $errorMessage);
                throw new StatusWebpayException($errorMessage, $token);
            }
            $errorMessage = 'Ocurrió un error al tratar de obtener el status ( token: '.$token.') de la transacción Webpay en Transbank: '.$e->getMessage();
            $this->errorExecutionTbkApi($orderId, 'status', $params, 'StatusWebpayException', $e->getMessage(), $errorMessage);
            throw new StatusWebpayException($errorMessage, $token);
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
            throw new CreateWebpayException($errorMessage);
        }
    }

    public function createTransaction($orderId, $amount, $returnUrl)
    {
        global $wpdb;
        $randomNumber = uniqid();
        $buyOrder = 'wc:'.$randomNumber.':'.$orderId;
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
            $errorMessage = 'Ocurrió un error al tratar de obtener la transacción aprobada con el "número de orden": "'.$orderId.'" desde la base de datos. Error: '.$e->getMessage();
            $this->errorExecution($orderId, 'create', [], 'GetTransactionWebpayException', $e->getMessage(), $errorMessage);
            throw new GetTransactionWebpayException($errorMessage, $orderId);
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
            throw new RefundWebpayException($errorMessage, $token, $tx);
        }
    }

    public function refundTransaction($orderId, $amount)//NotFoundRefundTransactionWebpayException
    {
        $params = [
            'orderId'  => $orderId,
            'amount'  => $amount
        ];
        /*1. Extraemos la transacción */
        $this->logInfoData('', 'Buscando una transacción aprobada ("approved") valida para hacer refund para la "orden": "'.$orderId.'" en la base de datos', $params);
        $tx = $this->getTransactionApprovedByOrderId($orderId);
        if (!$tx) {
            $errorMessage = 'No se encontró una transacción aprobada ("approved") valida para hacer refund para la "orden": "'.$orderId.'" en la base de datos';
            $this->errorExecution($orderId, 'refund', $params, 'NotFoundTransactionWebpayException', $errorMessage, $errorMessage);
            throw new NotFoundTransactionWebpayException($errorMessage, $orderId);
        }

        /*2. Realizamos el refund */
        $this->logInfoData($tx->buy_order, 'Preparando datos antes de hacer refund a la transacción en Transbank', [
            'orderId'  => $orderId,
            'amount'  => $amount,
            'transaction'  => $tx
        ]);
        $refundResponse = $this->refundInner($orderId, $tx->token, $amount, $tx);
        $this->logInfoData($tx->buy_order, 'Se hizo el refund a la transacción en Transbank', [
            'token'  => $tx->token,
            'amount'  => $amount,
            'transaction'  => $tx,
            'response'  => $refundResponse
        ]);

        /*3. Validamos si fue exitoso */
        if (!(($refundResponse->getType() === 'REVERSED' || $refundResponse->getType() === 'NULLIFIED') && (int) $refundResponse->getResponseCode() === 0)) {
            $errorMessage = 'El refund de la transacción ha sido rechazado por Transbank (código de respuesta: '.$refundResponse->getResponseCode().')';
            $this->errorExecution($orderId, 'refund', $params, 'RejectedRefundWebpayException', $errorMessage, $errorMessage);
            throw new RejectedRefundWebpayException($errorMessage, $tx->token, $tx, $refundResponse);
        }
        $this->logInfoData($tx->buy_order, '***** REFUND TBK OK *****', [
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

    public function processRequestFromTbkReturn($server, $get, $post)
    {
        $method = $server['REQUEST_METHOD'];
        $params = $method === 'GET' ? $get : $post;
        $tbkToken = isset($params["TBK_TOKEN"]) ? $params['TBK_TOKEN'] : null;
        $tbkSessionId = isset($params["TBK_ID_SESION"]) ? $params['TBK_ID_SESION'] : null;
        $tbkOrdenCompra = isset($params["TBK_ORDEN_COMPRA"]) ? $params['TBK_ORDEN_COMPRA'] : null;
        $tokenWs = isset($params["token_ws"]) ? $params['token_ws'] : null;

        $params1 = [
            'method' => $method,
            'params' => $params
        ];

        $this->logInfoData('', 'Retornando desde el sitio de Transbank para realizar el commit', $params1);

        if (!isset($tokenWs) && !isset($tbkToken)) {
            $errorMessage = 'La transacción fue cancelada automáticamente por estar inactiva mucho tiempo en el formulario de pago de Webpay. Puede reintentar el pago';
            $transaction = null;
            if (isset($tbkOrdenCompra) && isset($tbkSessionId)) {
                $transaction = Transaction::getByBuyOrderAndSessionId($tbkOrdenCompra, $tbkSessionId);
                $this->errorExecution(isset($transaction) ? $transaction->order_id : 0, 'commit', $params1, 'TimeoutWebpayException', $errorMessage, $errorMessage);
                $this->saveTransactionWithErrorByTransaction($transaction, 'TimeoutWebpayException', $errorMessage);
            } else {
                $this->errorExecution(0, 'commit', $params1, 'TimeoutWebpayException', $errorMessage, $errorMessage);
            }
            throw new TimeoutWebpayException($errorMessage, $tbkOrdenCompra, $tbkSessionId, $transaction);
        }
        
        if (!isset($tokenWs) && isset($tbkToken)) {
            $errorMessage = 'La transacción fue anulada por el usuario.';
            $transaction = $this->saveTransactionWithErrorByToken($tbkToken, 'UserCancelWebpayException', $errorMessage);
            $this->errorExecution($transaction->order_id, 'commit', $params1, 'UserCancelWebpayException', $errorMessage, $errorMessage);
            throw new UserCancelWebpayException($errorMessage, $tbkToken, $transaction);
        }

        if (isset($tbkToken) && isset($tokenWs)) {
            $errorMessage = 'El pago es inválido.';
            $transaction = $this->saveTransactionWithErrorByToken($tbkToken, 'DoubleTokenWebpayException', $errorMessage);
            $this->errorExecution($transaction->order_id, 'commit', $params1, 'DoubleTokenWebpayException', $errorMessage, $errorMessage);
            throw new DoubleTokenWebpayException($errorMessage, $tbkToken, $tokenWs, $transaction);
        }

        return Transaction::getByToken($tokenWs);
    }

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
            throw new CommitWebpayException($errorMessage, $token, $transaction);
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
            return $transaction;
        }
        $this->saveTransactionWithError($transaction->id, $error, $detailError);
        return $transaction;
    }

    public function saveTransactionWithErrorByToken($token, $error, $detailError)
    {
        return $this->saveTransactionWithErrorByTransaction(Transaction::getByToken($token), $error, $detailError);
    }

    

    
        /**
     * @param array $result
     * @param $webpayTransaction
     *
     * @return bool
     */
    /*
    protected function validateTransactionDetails($result, $webpayTransaction)
    {
        if (!isset($result->responseCode)) {
            return false;
        }

        return $result->buyOrder == $webpayTransaction->buy_order && $result->sessionId == $webpayTransaction->session_id && $result->amount == $webpayTransaction->amount;
    }*/
}
