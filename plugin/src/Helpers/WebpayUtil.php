<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use \Exception;
use Transbank\Webpay\Options;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;
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

class WebpayUtil {

    /* Metodo CREATE  */

    public static function createInner($environment, $commerceCode, $apiKey, $buyOrder, $sessionId, $amount, $returnUrl)
    {
        try {
            $webpayplusTransaction = new \Transbank\Webpay\WebpayPlus\Transaction(new Options($apiKey, $commerceCode, $environment));
            return $webpayplusTransaction->create($buyOrder, $sessionId, $amount, $returnUrl);
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al ejecutar el create de la transacción: '.$e->getMessage();
            WebpayUtil::logError($errorMessage);
            throw new CreateWebpayException($e->getMessage());
        }
    }


    public static function createTransaction($environment, $commerceCode, $apiKey, $orderId, $amount, $returnUrl)
    {
        global $wpdb;
        $randomNumber = uniqid();
        $buyOrder = 'wc:'.$randomNumber.':'.$orderId;
        $sessionId = 'wc:sessionId:'.$randomNumber.':'.$orderId;

        /*1. Creamos la transacción antes de crear la tx en TBK */
        $transaction = [
            'order_id'    => $orderId,
            'buy_order'   => $buyOrder,
            'amount'      => $amount,
            'session_id'  => $sessionId,
            'environment' => $environment,
            'product'     => Transaction::PRODUCT_WEBPAY_PLUS,
            'status'      => Transaction::STATUS_PREPARED,
        ];

        $insert = Transaction::createTransaction($transaction);
        /*2. Validamos que la insercion en la bd fue exitosa */
        if (!$insert) {
            $transactionTable = Transaction::getTableName();
            $wpdb->show_errors();
            $errorMessage = "La transacción no se pudo registrar en la tabla: '{$transactionTable}', query: {$wpdb->last_query}, error: {$wpdb->last_error}";
            OneclickUtil::logError($errorMessage);
            throw new CreateTransactionWebpayException($errorMessage);
        }
        $tx = Transaction::getByBuyOrder($buyOrder);
        if (!isset($tx)) {
            $errorMessage = "no se creo la transacción";
            OneclickUtil::logError($errorMessage);
            throw new CreateTransactionWebpayException($errorMessage);
        }

        /*3. Creamos la transaccion*/
        $createResponse = WebpayUtil::createInner($environment, $commerceCode, $apiKey, $buyOrder, $sessionId, $amount, $returnUrl);

        /*4. Validamos si es valida */
        if (!isset($createResponse) || !isset($createResponse->url) || !isset($createResponse->token)) {
            throw new CreateWebpayException('No se ha creado la transacción para, amount: '.$amount.', sessionId: '.$sessionId.', buyOrder: '.$buyOrder);
        }
        Transaction::update(
            $tx->id,
            [
                'token'  => $createResponse->token,
                'status' => Transaction::STATUS_INITIALIZED,
            ]
        );

        return $createResponse;
    }

    /* Metodo REFUND  */
    public static function getTransactionApprovedByOrderId($orderId)
    {
        try {
            return Transaction::getApprovedByOrderId($orderId);
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al tratar de obtener la transacción aprobada con el "número de orden": "'.$orderId.'" desde la base de datos. Error: '.$e->getMessage();
            WebpayUtil::logError($errorMessage);
            throw new GetTransactionWebpayException($errorMessage, $orderId);
        }
    }

    public static function refundInner($environment, $commerceCode, $apiKey, $token, $amount, $tx)
    {
        try {
            $webpayplusTransaction = new \Transbank\Webpay\WebpayPlus\Transaction(new Options($apiKey, $commerceCode, $environment));
            return $webpayplusTransaction->refund($token, $amount);
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al ejecutar el refund de la transacción en Webpay con el "token": "'.$token.'" y "monto": "'.$amount.'". Error: '.$e->getMessage();
            WebpayUtil::logError($errorMessage);
            throw new RefundWebpayException($errorMessage, $token, $tx);
        }
    }

    public static function refundTransaction($environment, $commerceCode, $apiKey, $orderId, $amount)//NotFoundRefundTransactionWebpayException
    {
        /*1. Extraemos la transacción */
        $tx = WebpayUtil::getTransactionApprovedByOrderId($orderId);
        if (!$tx) {
            $errorMessage = 'No se encontró una transacción aprobada con el "número de orden": "'.$orderId.'" en la base de datos';
            WebpayUtil::logError($errorMessage);
            throw new NotFoundTransactionWebpayException($errorMessage, $orderId);
        }

        /*2. Realizamos el refund */
        $refundResponse = WebpayUtil::refundInner($environment, $commerceCode, $apiKey, $tx->token, $amount, $tx);

        /*3. Validamos si fue exitoso */
        if (!(($refundResponse->getType() === 'REVERSED' || $refundResponse->getType() === 'NULLIFIED') && (int) $refundResponse->getResponseCode() === 0)) {
            $errorMessage = 'El refund de la transacción no se pudo realizar en Webpay con el "token": "'.$tx->token.'" y "monto": "'.$amount.'". ';
            WebpayUtil::logError($errorMessage);
            throw new RejectedRefundWebpayException($errorMessage, $tx->token, $tx, $refundResponse);
        }
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

    public static function processRequestFromTbkReturn($server, $get, $post)
    {
        $method = $server['REQUEST_METHOD'];
        $params = $method === 'GET' ? $get : $post;
        $tbkToken = isset($params["TBK_TOKEN"]) ? $params['TBK_TOKEN'] : null;
        $tbkSessionId = isset($params["TBK_ID_SESION"]) ? $params['TBK_ID_SESION'] : null;
        $tbkOrdenCompra = isset($params["TBK_ORDEN_COMPRA"]) ? $params['TBK_ORDEN_COMPRA'] : null;
        $tokenWs = isset($params["token_ws"]) ? $params['token_ws'] : null;

        if (!isset($tokenWs) && !isset($tbkToken)) {
            $errorMessage = 'La transacción fue cancelada automáticamente por estar inactiva mucho tiempo en el formulario de pago de Webpay. Puede reintentar el pago';
            WebpayUtil::logError($errorMessage);
            $transaction = null;
            if (isset($tbkOrdenCompra) && isset($tbkSessionId)) {
                $transaction = Transaction::getByBuyOrderAndSessionId($tbkOrdenCompra, $tbkSessionId);
                WebpayUtil::saveTransactionWithErrorByTransaction($transaction, 'TimeoutWebpayException', $errorMessage);
            }
            throw new TimeoutWebpayException($errorMessage, $tbkOrdenCompra, $tbkSessionId, $transaction);
        }
        
        if (!isset($tokenWs) && isset($tbkToken)) {
            $errorMessage = 'La transacción fue anulada por el usuario.';
            WebpayUtil::logError($errorMessage);
            $transaction = WebpayUtil::saveTransactionWithErrorByToken($tbkToken, 'UserCancelWebpayException', $errorMessage);
            throw new UserCancelWebpayException($errorMessage, $tbkToken, $transaction);
        }

        if (isset($tbkToken) && isset($tokenWs)) {
            $errorMessage = 'El pago es inválido.';
            WebpayUtil::logError($errorMessage);
            $transaction = WebpayUtil::saveTransactionWithErrorByToken($tbkToken, 'DoubleTokenWebpayException', $errorMessage);
            throw new DoubleTokenWebpayException($errorMessage, $tbkToken, $tokenWs, $transaction);
        }

        return Transaction::getByToken($tokenWs);
    }

    public static function commitInner($environment, $commerceCode, $apiKey, $token)
    {
        try {
            $webpayplusTransaction = new \Transbank\Webpay\WebpayPlus\Transaction(new Options($apiKey, $commerceCode, $environment));
            return $webpayplusTransaction->commit($token);
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al ejecutar el commit de la transacción: '.$e->getMessage();
            WebpayUtil::logError($errorMessage);
            $transaction = WebpayUtil::saveTransactionWithErrorByToken($token, 'CommitWebpayException', $errorMessage);
            throw new CommitWebpayException($e->getMessage(), $token, $transaction);
        }
    }

    public static function commitTransaction($environment, $commerceCode, $apiKey, $token)
    {
        $transaction = Transaction::getByToken($token);
        if ($transaction->status !== Transaction::STATUS_INITIALIZED) {
            $errorMessage = 'La transacción no se encuentra en estado inicializada: '.$token;
            WebpayUtil::logError($errorMessage);
            throw new InvalidStatusWebpayException($errorMessage, $token, $transaction);
        }
        $commitResponse = WebpayUtil::commitInner($environment, $commerceCode, $apiKey, $token);
        if (!$commitResponse->isApproved()) {
            $errorMessage = 'La transacción ha sido rechazada (código de respuesta: '.$commitResponse->getResponseCode().')';
            WebpayUtil::logError($errorMessage);
            WebpayUtil::saveTransactionWithError($transaction->id, 'RejectedCommitWebpayException', $errorMessage, $commitResponse);
            throw new RejectedCommitWebpayException($errorMessage, $token, $transaction, $commitResponse);
        }
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

    public static function saveTransactionWithError($txId, $error, $detailError, $commitResponse = null)
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

    public static function saveTransactionWithErrorByTransaction($transaction, $error, $detailError)
    {
        if ($transaction->status !== Transaction::STATUS_INITIALIZED) {
            $errorMessage = 'Se quiso guardar la excepción: '.$error.' ('.$detailError.') '.' y la transacción no se encuentra en estado inicializada: '.$transaction->token;
            WebpayUtil::logError($errorMessage);
            return $transaction;
        }
        WebpayUtil::saveTransactionWithError($transaction->id, $error, $detailError);
        return $transaction;
    }

    public static function saveTransactionWithErrorByToken($token, $error, $detailError)
    {
        return WebpayUtil::saveTransactionWithErrorByTransaction(Transaction::getByToken($token), $error, $detailError);
    }

    public static function logInfo($str)
    {
        (new LogHandler())->logInfo($str);
    }

    public static function logError($str)
    {
        (new LogHandler())->logError($str);
    }


        /**
     * @param array $result
     * @param $webpayTransaction
     *
     * @return bool
     */
    protected function validateTransactionDetails($result, $webpayTransaction)
    {
        if (!isset($result->responseCode)) {
            return false;
        }

        return $result->buyOrder == $webpayTransaction->buy_order && $result->sessionId == $webpayTransaction->session_id && $result->amount == $webpayTransaction->amount;
    }






}
