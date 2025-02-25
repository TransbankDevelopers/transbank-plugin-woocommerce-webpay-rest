<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\Plugin\Exceptions\EcommerceException;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\WebpayplusTransbankSdk;
use Transbank\Plugin\Exceptions\Webpay\AlreadyProcessedException;
use Transbank\Plugin\Exceptions\Webpay\TimeoutWebpayException;
use Transbank\Plugin\Exceptions\Webpay\UserCancelWebpayException;
use Transbank\Plugin\Exceptions\Webpay\DoubleTokenWebpayException;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;

class TransactionResponseHandler
{
    const WEBPAY_NORMAL_FLOW = 'Normal';
    const WEBPAY_TIMEOUT_FLOW = 'Timeout';
    const WEBPAY_ABORTED_FLOW = 'Aborted';
    const WEBPAY_ERROR_FLOW = 'Error';
    const WEBPAY_INVALID_FLOW = 'Invalid';

    const WEBPAY_ALREADY_PROCESSED_MESSAGE = 'La transacción fue procesada anteriormente.';
    const WEBPAY_FAILED_FLOW_MESSAGE = 'Transacción no autorizada.';
    const WEBPAY_TIMEOUT_FLOW_MESSAGE = 'Tiempo excedido en el formulario de Webpay.';
    const WEBPAY_ABORTED_FLOW_MESSAGE = 'Orden anulada por el usuario.';
    const WEBPAY_ERROR_FLOW_MESSAGE = 'Orden cancelada por un error en el formulario de pago.';

    protected WebpayplusTransbankSdk $webpayPlusTransaction;

    public function __construct()
    {
        $this->webpayPlusTransaction = TbkFactory::createWebpayplusTransbankSdk();
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
            $this->webpayPlusTransaction->logInfoData($buyOrder, $logMessage, $transaction);
            throw new AlreadyProcessedException($logMessage, $transaction, self::WEBPAY_NORMAL_FLOW);
        }

        if ($status == Transaction::STATUS_TIMEOUT) {
            $logMessage = self::WEBPAY_TIMEOUT_FLOW_MESSAGE;
            $this->webpayPlusTransaction->logInfoData($buyOrder, $logMessage, $transaction);
            throw new AlreadyProcessedException($logMessage, $transaction, self::WEBPAY_TIMEOUT_FLOW);
        }

        if ($status == Transaction::STATUS_ABORTED_BY_USER) {
            $logMessage = self::WEBPAY_ABORTED_FLOW_MESSAGE;
            $this->webpayPlusTransaction->logInfoData($buyOrder, $logMessage, $transaction);
            throw new AlreadyProcessedException($logMessage, $transaction, self::WEBPAY_ABORTED_FLOW);
        }

        if ($status == Transaction::STATUS_FAILED) {
            $logMessage = self::WEBPAY_FAILED_FLOW_MESSAGE;
            $this->webpayPlusTransaction->logInfoData($buyOrder, $logMessage, $transaction);
            throw new AlreadyProcessedException($logMessage, $transaction, self::WEBPAY_ERROR_FLOW);
        }
    }

    public function handleRequestFromTbkReturn(array $request)
    {
        $tokenWs = $params['token_ws'] ?? null;
        $tbkToken = $params['TBK_TOKEN'] ?? null;
        $sessionId = $params['TBK_ID_SESION'] ?? null;
        $buyOrder = $params['TBK_ORDEN_COMPRA'] ?? null;
        $webpayFlow = $this->getWebpayFlow($request);

        if ($webpayFlow == self::WEBPAY_NORMAL_FLOW) {
            $this->handleNormalFlow($tokenWs);
        }

        if ($webpayFlow == self::WEBPAY_TIMEOUT_FLOW) {
            $this->handleTimeoutFlow($sessionId, $buyOrder);
        }

        if ($webpayFlow == self::WEBPAY_ABORTED_FLOW) {
            $this->handleAbortedFlow($tbkToken);
        }

        if ($webpayFlow == self::WEBPAY_ERROR_FLOW) {
            $this->handleErrorFlow($tokenWs, $tbkToken);
        }

        if ($webpayFlow == self::WEBPAY_INVALID_FLOW) {
            throw new EcommerceException('Flujo de pago no reconocido.');
        }
    }

    private function getWebpayFlow(array $request): string
    {
        $tokenWs = $request['token_ws'] ?? null;
        $tbkToken = $request['TBK_TOKEN'] ?? null;
        $tbkSessionId = $request['TBK_ID_SESION'] ?? null;
        $webpayFlow = self::WEBPAY_INVALID_FLOW;

        if (isset($tokenWs) && isset($tbkToken)) {
            return self::WEBPAY_ERROR_FLOW;
        }

        if ($tbkToken && $tbkSessionId && !$tokenWs) {
            $webpayFlow = self::WEBPAY_ABORTED_FLOW;
        }

        if ($tbkSessionId && !$tbkToken && !$tokenWs) {
            $webpayFlow = self::WEBPAY_TIMEOUT_FLOW;
        }

        if ($tokenWs && !$tbkToken && !$tbkSessionId) {
            $webpayFlow = self::WEBPAY_NORMAL_FLOW;
        }

        return $webpayFlow;
    }

    private function handleNormalFlow(string $token)
    {
        $this->webpayPlusTransaction->logInfo("Flujo normal detectado, token: $token");

        if ($this->isTransactionProcessed($token)) {
            $this->handleProcessedTransaction($token);
        }

        return Transaction::getByToken($token);
    }

    private function handleTimeoutFlow(string $sessionId, string $buyOrder)
    {
        $this->webpayPlusTransaction->logInfo("Flujo de timeout detectado, sessionId: $sessionId, buyOrder: $buyOrder");
        $transaction = Transaction::getByBuyOrderAndSessionId($buyOrder, $sessionId) ?? null;
        $token = $transaction->token ?? null;

        if ($this->isTransactionProcessed($token)) {
            $this->handleProcessedTransaction($token);
        }

        $errorMessage = self::WEBPAY_TIMEOUT_FLOW_MESSAGE;
        $orderId = $transaction->order_id ?? 0;
        $data = [
            'TBK_ID_SESION' => $sessionId,
            'TBK_ORDEN_COMPRA' => $buyOrder
        ];
        $this->webpayPlusTransaction->errorExecution($orderId, 'commit', $data, 'TimeoutWebpayException', $errorMessage, $errorMessage);
        $this->webpayPlusTransaction->saveTransactionWithErrorByTransaction($transaction, 'TimeoutWebpayException', $errorMessage);
        throw new TimeoutWebpayException($errorMessage, $buyOrder, $sessionId, $transaction);
    }

    private function handleAbortedFlow(string $token)
    {
        $this->webpayPlusTransaction->logInfo("Flujo de pago abortado detectado, token: $token");

        if ($this->isTransactionProcessed($token)) {
            $this->handleProcessedTransaction($token);
        }

        $errorMessage = self::WEBPAY_ABORTED_FLOW_MESSAGE;
        $transaction = $this->webpayPlusTransaction->saveTransactionWithErrorByToken($token, 'UserCancelWebpayException', $errorMessage);
        $this->webpayPlusTransaction->errorExecution($transaction->order_id, 'commit', $token, 'UserCancelWebpayException', $errorMessage, $errorMessage);
        throw new UserCancelWebpayException($errorMessage, $token, $transaction);
    }

    private function handleErrorFlow(string $token, string $tbkToken)
    {
        $this->webpayPlusTransaction->logInfo("Flujo con error en el formulario detectado, token: $token");

        if ($this->isTransactionProcessed($token)) {
            $this->handleProcessedTransaction($token);
        }

        $errorMessage = self::WEBPAY_ERROR_FLOW_MESSAGE;
        $transaction = $this->webpayPlusTransaction->saveTransactionWithErrorByToken($tbkToken, 'DoubleTokenWebpayException', $errorMessage);
        $this->webpayPlusTransaction->errorExecution($transaction->order_id, 'commit', $token, 'DoubleTokenWebpayException', $errorMessage, $errorMessage);
        throw new DoubleTokenWebpayException($errorMessage, $tbkToken, $token, $transaction);
    }
}
