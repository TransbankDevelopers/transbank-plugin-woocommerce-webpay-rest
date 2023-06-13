<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use \Exception;
use Transbank\Webpay\Oneclick\MallInscription;
use Transbank\Webpay\Oneclick\MallTransaction;
use Transbank\Webpay\Options;
use Transbank\WooCommerce\WebpayRest\Models\Inscription;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;
use Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\TimeoutInscriptionOneclickException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\UserCancelInscriptionOneclickException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\WithoutTokenInscriptionOneclickException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\RejectedInscriptionOneclickException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\InvalidStatusInscriptionOneclickException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\FinishInscriptionOneclickException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\GetInscriptionOneclickException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\RejectedAuthorizeOneclickException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\ConstraintsViolatedAuthorizeOneclickException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\CreateTransactionOneclickException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\AuthorizeOneclickException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\RejectedRefundOneclickException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\RefundOneclickException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\NotFoundTransactionOneclickException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick\GetTransactionOneclickException;

class OneclickUtil {
    
    /**
     * @return bool
     */
    public static function processRequestFromTbkReturn($server, $get, $post)
    {
        $method = $server['REQUEST_METHOD'];
        $params = $method === 'GET' ? $get : $post;
        $tbkToken = isset($params["TBK_TOKEN"]) ? $params['TBK_TOKEN'] : null;
        $tbkSessionId = isset($params["TBK_ID_SESION"]) ? $params['TBK_ID_SESION'] : null;
        $tbkOrdenCompra = isset($params["TBK_ORDEN_COMPRA"]) ? $params['TBK_ORDEN_COMPRA'] : null;

        OneclickUtil::logOneclickInscriptionRetornandoDesdeTbk($method, $params);

        if (!isset($tbkToken)) {
            $error = 'No se recibió el token de la inscripción.';
            OneclickUtil::logError($error);
            throw new WithoutTokenInscriptionOneclickException($error);
        }

        if ($tbkOrdenCompra && $tbkSessionId && !$tbkToken) {
            $error = 'La inscripción fue cancelada automáticamente por estar inactiva mucho tiempo.';
            OneclickUtil::logError($error);
            $inscription = OneclickUtil::saveInscriptionWithError($tbkToken, $error);
            throw new TimeoutInscriptionOneclickException($error, $tbkToken, $inscription);
        }
        
        if (isset($tbkOrdenCompra)) {
            $error = 'La inscripción fue anulada por el usuario o hubo un error en el formulario de inscripción.';
            OneclickUtil::logError($error);
            $inscription = OneclickUtil::saveInscriptionWithError($tbkToken, $error);
            throw new UserCancelInscriptionOneclickException($error, $tbkToken, $inscription);
        }

        return $tbkToken;
    }

    public static function processTbkReturnAndFinishInscription($server, $get, $post)
    {
        $tbkToken = OneclickUtil::processRequestFromTbkReturn($server, $get, $post);
        $inscription = Inscription::getByToken($tbkToken);

        if ($inscription->status !== Inscription::STATUS_INITIALIZED) {
            $error = 'La inscripción no se encuentra en estado inicializada: '.$tbkToken;
            OneclickUtil::logError($error);
            throw new InvalidStatusInscriptionOneclickException($error, $tbkToken, $inscription);
        }
        $finishInscriptionResponse = OneclickUtil::finishInscription($tbkToken, $inscription);
        if (!$finishInscriptionResponse->isApproved()) {
            $error = 'La inscripción de la tarjeta ha sido rechazada (código de respuesta: '.$finishInscriptionResponse->getResponseCode().')';
            OneclickUtil::logError($error);
            throw new RejectedInscriptionOneclickException($error, $tbkToken, $inscription, $finishInscriptionResponse);
        }
        return array(
            'inscription' => $inscription,
            'finishInscriptionResponse' => $finishInscriptionResponse
        );
    }

    public static function logOneclickInscriptionRetornandoDesdeTbk($method, $params)
    {
        OneclickUtil::logInfo('Iniciando validación luego de redirección desde tbk => method: '.$method);
        OneclickUtil::logInfo(json_encode($params));
    }

    public static function logInfo($str)
    {
        (new LogHandler())->logInfo($str);
    }

    public static function logError($str)
    {
        (new LogHandler())->logError($str);
    }

    public static function getInscriptionByToken($tbkToken)
    {
        try {
            return Inscription::getByToken($tbkToken);
        } catch (Exception $e) {
            $error = 'Ocurrió un error al obtener la inscripción: '.$e->getMessage();
            OneclickUtil::logError($error);
            throw new GetInscriptionOneclickException($error);
        }
    }

    public static function finishInscription($tbkToken, $inscription)
    {
        try {
            $finishInscriptionResponse = (new MallInscription())->finish($tbkToken);
            Inscription::update($inscription->id, [
                'finished'           => true,
                'authorization_code' => $finishInscriptionResponse->getAuthorizationCode(),
                'card_type'          => $finishInscriptionResponse->getCardType(),
                'card_number'        => $finishInscriptionResponse->getCardNumber(),
                'transbank_response' => json_encode($finishInscriptionResponse),
                'status'             => $finishInscriptionResponse->isApproved() ? Inscription::STATUS_COMPLETED : Inscription::STATUS_FAILED,
            ]);
            return $finishInscriptionResponse;
        } catch (Exception $e) {
            $error = 'Ocurrió un error al ejecutar la inscripción: '.$e->getMessage();
            OneclickUtil::logError($error);
            $ins = OneclickUtil::saveInscriptionWithError($tbkToken, $error);
            throw new FinishInscriptionOneclickException($e->getMessage(), $tbkToken, $ins);
        }
    }

    public static function saveInscriptionWithError($tbkToken, $error)
    {
        $inscription = Inscription::getByToken($tbkToken);
        if ($inscription == null) {
            return null;
        }
        Inscription::update($inscription->id, [
            'status' => Inscription::STATUS_FAILED
        ]);
        return $inscription;
    }



    public static function authorizeInner($environment, $commerceCode, $apiKey, $childCommerceCode, $parentBuyOrder, $childBuyOrder, $amount, $username, $token, $txId)
    {
        try {
            $mallTransaction = new MallTransaction(new Options($apiKey, $commerceCode, $environment));
            $details = [
                [
                    'commerce_code'       => $childCommerceCode,
                    'buy_order'           => $childBuyOrder,
                    'amount'              => $amount,
                    'installments_number' => 1,
                ],
            ];
            /*3. Autorizamos el pago*/
            return $mallTransaction->authorize(
                $username,
                $token,
                $parentBuyOrder,
                $details
            );
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al ejecutar la autorización: '.$e->getMessage();
            OneclickUtil::logError($errorMessage);
            OneclickUtil::saveTransactionWithError($txId, 'AuthorizeOneclickException', $errorMessage);
            throw new AuthorizeOneclickException($e->getMessage());
        }
    }


    public static function authorize($environment, $commerceCode, $apiKey, $childCommerceCode, $orderId, $amount, $username, $token) {
        global $wpdb;
        $randomNumber = uniqid();
        $parentBuyOrder = 'wc:'.$randomNumber.':'.$orderId;
        $childBuyOrder = 'wc:child:'.$randomNumber.':'.$orderId;

        /*1. Creamos la transacción antes de autorizar en TBK */
        $insert = Transaction::createTransaction([
            'order_id'            => $orderId,
            'buy_order'           => $parentBuyOrder,
            'child_buy_order'     => $childBuyOrder,
            'commerce_code'       => $commerceCode,
            'child_commerce_code' => $childCommerceCode,
            'amount'              => $amount,
            'environment'         => $environment,
            'product'             => Transaction::PRODUCT_WEBPAY_ONECLICK,
            'status'              => Transaction::STATUS_INITIALIZED
        ]);

        /*2. Validamos que la insercion en la bd fue exitosa */
        if (!$insert) {
            $transactionTable = Transaction::getTableName();
            $wpdb->show_errors();
            $errorMessage = "La transacción no se pudo registrar en la tabla: '{$transactionTable}', query: {$wpdb->last_query}, error: {$wpdb->last_error}";
            OneclickUtil::logError($errorMessage);
            throw new CreateTransactionOneclickException($errorMessage);
        }
        $tx = Transaction::getByBuyOrder($parentBuyOrder);
        if (!isset($tx)) {
            $errorMessage = "no se creo la transacción";
            OneclickUtil::logError($errorMessage);
            throw new CreateTransactionOneclickException($errorMessage);
        }

        /*3. Autorizamos el pago*/
        $authorizeResponse = OneclickUtil::authorizeInner($environment, $commerceCode, $apiKey, $childCommerceCode, $parentBuyOrder, $childBuyOrder, $amount, $username, $token, $tx->id);
        $transbankStatus = $authorizeResponse->getDetails()[0]->getStatus() ?? null;

        /*4. Validamos si esta aprobada */
        if (!$authorizeResponse->isApproved()) {
            if ($transbankStatus === 'CONSTRAINTS_VIOLATED') {
                $errorMessage = 'La transacción ha sido rechazada porque se superó el monto máximo por transacción, el monto máximo diario o el número de transacciones diarias configuradas por el comercio para cada usuario';
                OneclickUtil::logError($errorMessage);
                OneclickUtil::saveTransactionWithError($tx->id, 'ConstraintsViolatedAuthorizeOneclickException', $errorMessage);
                throw new ConstraintsViolatedAuthorizeOneclickException($errorMessage, $authorizeResponse);
            } else {
                $errorCode = $authorizeResponse->getDetails()[0]->getResponseCode() ?? null;
                $errorMessage = 'La transacción ha sido rechazada (Código de error: '.$errorCode.')';
                OneclickUtil::logError($errorMessage);
                OneclickUtil::saveTransactionWithError($tx->id, 'RejectedAuthorizeOneclickException', $errorMessage);
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

    public static function saveTransactionWithError($txId, $error, $detailError)
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
    public static function getTransactionApprovedByOrderId($orderId)
    {
        try {
            return Transaction::getApprovedByOrderId($orderId);
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al tratar de obtener la transacción aprobada ("orderId": "'.$orderId.'") desde la base de datos. Error: '.$e->getMessage();
            OneclickUtil::logError($errorMessage);
            throw new GetTransactionOneclickException($errorMessage, $orderId);
        }
    }

    public static function refundInner($environment, $commerceCode, $apiKey, $childCommerceCode, $amount, $transaction)
    {
        try {
            $mallTransaction = new MallTransaction(new Options($apiKey, $commerceCode, $environment));
            return $mallTransaction->refund($transaction->buy_order, $childCommerceCode, $transaction->child_buy_order, $amount);
        } catch (Exception $e) {
            $errorMessage = 'Ocurrió un error al ejecutar el refund de la transacción en Webpay ("buyOrder": "'.$transaction->buy_order.'", "childBuyOrder": "'.$transaction->child_buy_order.'", "amount": "'.$amount.'"). Error: '.$e->getMessage();
            OneclickUtil::logError($errorMessage);
            throw new RefundOneclickException($errorMessage, $transaction->buy_order, $transaction->child_buy_order, $transaction);
        }
    }

    public static function refundTransaction($environment, $commerceCode, $apiKey, $childCommerceCode, $orderId, $amount)
    {
        /*1. Extraemos la transacción */
        $tx = OneclickUtil::getTransactionApprovedByOrderId($orderId);
        if (!$tx) {
            $errorMessage = 'No se encontró una transacción aprobada ("orderId": "'.$orderId.'") en la base de datos';
            OneclickUtil::logError($errorMessage);
            throw new NotFoundTransactionOneclickException($errorMessage, $orderId);
        }

        /*2. Realizamos el refund */
        $refundResponse = OneclickUtil::refundInner($environment, $commerceCode, $apiKey, $childCommerceCode, $amount, $tx);

        /*3. Validamos si fue exitoso */
        if (!(($refundResponse->getType() === 'REVERSED' || $refundResponse->getType() === 'NULLIFIED') && (int) $refundResponse->getResponseCode() === 0)) {
            $errorMessage = 'El refund de la transacción no se pudo realizar en Webpay ("buyOrder": "'.$tx->buy_order.'", "childBuyOrder": "'.$tx->child_buy_order.'", "amount"'.$amount.'". ';
            OneclickUtil::logError($errorMessage);
            throw new RejectedRefundOneclickException($errorMessage, $tx->buy_order, $tx->child_buy_order, $tx, $refundResponse);
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

}
