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
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\RejectedWebpayException;

class WebpayUtil {

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
            WebpayUtil::saveTransactionWithError($transaction->id, 'RejectedWebpayException', $errorMessage, $commitResponse);
            throw new RejectedWebpayException($errorMessage, $token, $transaction, $commitResponse);
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
    

