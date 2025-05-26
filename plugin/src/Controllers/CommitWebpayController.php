<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Transbank\Plugin\Repositories\TransactionRepositoryInterface;
use Transbank\Plugin\Services\WebpayService;
use Transbank\Plugin\Helpers\ILogger;
use Transbank\Plugin\Helpers\TbkConstants;
use Transbank\Plugin\Exceptions\EcommerceException;
use Transbank\Webpay\WebpayPlus\Responses\TransactionCommitResponse;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Services\EcommerceService;

class CommitWebpayController
{
    const WEBPAY_NORMAL_FLOW = 'normal';
    const WEBPAY_TIMEOUT_FLOW = 'timeout';
    const WEBPAY_ABORTED_FLOW = 'aborted';
    const WEBPAY_ERROR_FLOW = 'error';
    const WEBPAY_INVALID_FLOW = 'invalid';

    const WEBPAY_FAILED_FLOW_MESSAGE = 8;
    const WEBPAY_CANCELED_BY_USER_FLOW_MESSAGE = 9;
    const WEBPAY_TIMEOUT_FLOW_MESSAGE = 10;
    const WEBPAY_ERROR_FLOW_MESSAGE = 11;
    const WEBPAY_EXCEPTION_FLOW_MESSAGE = 12;
    const WEBPAY_CART_MANIPULATED_MESSAGE = 13;

    const ERROR_MESSAGES = [
        7 => 'Transacción aprobada',
        8 => 'Tu transacción no pudo ser autorizada. Ningún cobro fue realizado.',
        9 => 'Orden cancelada por el usuario. Por favor, reintente el pago.',
        10 => 'Orden cancelada por inactividad del usuario en el formulario de pago. Por favor, reintente el pago.',
        11 => 'Orden cancelada por un error en el formulario de pago. Por favor, reintente el pago.',
        12 => 'No se pudo procesar el pago. Si el problema persiste, contacte al comercio.',
        13 => 'El monto del carro ha cambiado mientras se procesaba el pago, la transacción fue cancelada. Ningún cobro fue realizado.',
    ];


    /**
     * @var ILogger
     */
    protected $log;
    protected TransactionRepositoryInterface $transactionRepository;
    protected WebpayService $webpayService;
    protected EcommerceService $ecommerceService;

    /**
     * Constructor initializes the logger.
     */
    public function __construct()
    {
        $this->log = TbkFactory::createLogger();
        $this->transactionRepository = TbkFactory::createTransactionRepository();
        $this->webpayService = TbkFactory::createWebpayService();
        $this->ecommerceService = TbkFactory::createEcommerceService();
    }

    protected function logError($msg)
    {
        $this->log->logError($msg);
    }

    protected function logInfo($msg)
    {
        $this->log->logInfo($msg);
    }

    public function proccess(): void
    {
        try {
            $requestMethod = $_SERVER['REQUEST_METHOD'];
            $request = $requestMethod === 'POST' ? $_POST : $_GET;
            $requestPayload = json_encode($request);
            $this->logInfo('Procesando retorno desde formulario de Webpay.');
            $this->logInfo("Request method: {$requestMethod}");
            $this->logInfo("Request payload: {$requestPayload}");
            $this->handleRequest($request);
        } catch (\Exception | \Error $e) {
            $this->logError('Error en el proceso de validación de pago: ' . $e->getMessage());
            $this->setPaymentErrorPage(self::WEBPAY_EXCEPTION_FLOW_MESSAGE);
        }
    }

    /**
     * Handles the payment flow based on the incoming request.
     *
     * @param array $request The request data from the payment gateway.
     *
     * @throws EcommerceException If the payment flow is not recognized.
     * @return void
     */
    protected function handleRequest(array $request): void
    {
        $webpayFlow = $this->getWebpayFlow($request);

        if ($webpayFlow == self::WEBPAY_NORMAL_FLOW) {
            $this->handleNormalFlow($request['token_ws']);
        }

        if ($webpayFlow == self::WEBPAY_TIMEOUT_FLOW) {
            $this->handleFlowTimeout($request['TBK_ORDEN_COMPRA']);
        }

        if ($webpayFlow == self::WEBPAY_ABORTED_FLOW) {
            $this->handleFlowAborted($request['TBK_TOKEN']);
        }

        if ($webpayFlow == self::WEBPAY_ERROR_FLOW) {
            $this->handleFlowError($request['token_ws']);
        }

        if ($webpayFlow == self::WEBPAY_INVALID_FLOW) {
            throw new EcommerceException('Flujo de pago no reconocido.');
        }
    }

    /**
     * Determines the type of payment flow based on the request data.
     *
     * @param array $request The request data from the payment gateway.
     * @return string The type of payment flow.
     */
    protected function getWebpayFlow(array $request): string
    {
        $tokenWs = $request['token_ws'] ?? null;
        $tbkToken = $request['TBK_TOKEN'] ?? null;
        $tbkIdSession = $request['TBK_ID_SESION'] ?? null;
        $webpayFlow = self::WEBPAY_INVALID_FLOW;

        if (isset($tokenWs) && isset($tbkToken)) {
            return self::WEBPAY_ERROR_FLOW;
        }

        if (isset($tbkIdSession) && isset($tbkToken) && !isset($tokenWs)) {
            $webpayFlow = self::WEBPAY_ABORTED_FLOW;
        }

        if (isset($tbkIdSession) && !isset($tbkToken) && !isset($tokenWs)) {
            $webpayFlow = self::WEBPAY_TIMEOUT_FLOW;
        }

        if (isset($tokenWs) && !isset($tbkToken) && !isset($tbkIdSession)) {
            $webpayFlow = self::WEBPAY_NORMAL_FLOW;
        }

        return $webpayFlow;
    }

    /**
     * Processes the normal payment flow. The result of the transaction can be approved or rejected.
     *
     * @param string $token The transaction token.
     * @return void
     */
    protected function handleNormalFlow(string $token): void
    {
        $this->logInfo("Procesando transacción por flujo Normal => token: {$token}");
        
        if ($this->transactionRepository->checkIsAlreadyProcessed($token)) {
            $this->handleTransactionAlreadyProcessed($token);
            return;
        }
        
        $webpayTransaction = $this->transactionRepository->findFirstByToken($token);
        $wooCommerceOrder = $this->ecommerceService->getOrderById($webpayTransaction->order_id);
        $commitResponse = $this->webpayService->commitTransaction($token);

        if ($commitResponse->isApproved()) {
            $this->handleAuthorizedTransaction(
                $wooCommerceOrder,
                $webpayTransaction,
                $commitResponse
            );
        } else {
           $this->handleUnauthorizedTransaction($webpayTransaction, $commitResponse);
        }
    }

    /**
     * Processes the payment flow when the transaction times out.
     *
     * @param string $buyOrder The buy order identifier.
     * @return void
     */
    protected function handleFlowTimeout(string $buyOrder): void
    {
        $this->logInfo("Procesando transacción por flujo timeout => Orden de compra: {$buyOrder}");

        $webpayTransaction = $this->transactionRepository->getByBuyOrder($buyOrder);

        if ($this->checkTransactionIsAlreadyProcessedByStatus($webpayTransaction->status)) {
            $this->handleTransactionAlreadyProcessed($webpayTransaction->token);
            return;
        }

        $this->handleAbortedTransaction(
            'transbank_webpay_plus_timeout_on_form',
            TbkConstants::TRANSACTION_STATUS_TIMEOUT,
            self::WEBPAY_TIMEOUT_FLOW_MESSAGE,
            $webpayTransaction
        );
    }

    /**
     * Processes the payment flow when the user aborts the transaction.
     *
     * @param string $token The transaction token.
     * @return void
     */
    protected function handleFlowAborted(string $token): void
    {
        $this->logInfo("Procesando transacción por flujo de pago abortado => Token: {$token}");

        $webpayTransaction = $this->transactionRepository->findFirstByToken($token);

        if ($this->checkTransactionIsAlreadyProcessedByStatus($webpayTransaction->status)) {
            $this->handleTransactionAlreadyProcessed($token);
            return;
        }

        $this->handleAbortedTransaction(
            'transbank_webpay_plus_transaction_cancelled_by_user',
            TbkConstants::TRANSACTION_STATUS_ABORTED_BY_USER,
            self::WEBPAY_CANCELED_BY_USER_FLOW_MESSAGE,
            $webpayTransaction
        );
    }

    /**
     * Processes the payment flow when an error occurs during the transaction.
     *
     * @param string $token The transaction token.
     * @return void
     */
    protected function handleFlowError(string $token): void
    {
        $this->logInfo(
            "Procesando transacción por flujo de error en formulario de pago => Token: {$token}"
        );

        $webpayTransaction = $this->transactionRepository->findFirstByToken($token);

        if ($this->transactionRepository->checkIsAlreadyProcessed($token)) {
            $this->handleTransactionAlreadyProcessed($token);
            return;
        }

        $this->handleAbortedTransaction(
            'transbank_webpay_plus_unexpected_error',
            TbkConstants::TRANSACTION_STATUS_ERROR,
            self::WEBPAY_ERROR_FLOW_MESSAGE,
            $webpayTransaction
        );
    }

    /**
     * Handles the case when the transaction is authorized by Transbank.
     *
     * @param $order The cart object.
     * @param $webpayTransaction The Webpay transaction object.
     * @param $commitResponse The commit response from Transbank.
     *
     * @throws EcommerceException
     * @return void
     */
    protected function handleAuthorizedTransaction(
        $wooCommerceOrder,
        $webpayTransaction,
        $commitResponse
    ): void {
        $token = $webpayTransaction->token;
        $this->logInfo("Transacción autorizada por Transbank, procesando orden con token: {$token}");

        $this->transactionRepository->update(
            $webpayTransaction->id,
            [
                'status'             => TbkConstants::TRANSACTION_STATUS_APPROVED,
                'transbank_status'   => $commitResponse->getStatus(),
                'transbank_response' => json_encode($commitResponse)
            ]
        );
        $this->ecommerceService->completeWebpayOrder($wooCommerceOrder, $commitResponse, $webpayTransaction);
        $this->doAction(
            'wc_transbank_webpay_plus_transaction_approved',
            $wooCommerceOrder,
            $webpayTransaction,
            $commitResponse);
        $this->redirect($wooCommerceOrder->get_checkout_order_received_url());
    }

    /**
     * Handles the case when the transaction is unauthorized by Transbank.
     *
     * @param $webpayTransaction The Webpay transaction object.
     * @param $commitResponse The commit response from Transbank.
     *
     * @throws EcommerceException
     * @return void
     */
    protected function handleUnauthorizedTransaction(
        $webpayTransaction,
        TransactionCommitResponse $commitResponse
    ): void {
        $token = $webpayTransaction->token;
        $this->logInfo("Transacción rechazada por Transbank con token: {$token}");

        $wooCommerceOrder = $this->ecommerceService->getOrderById($webpayTransaction->order_id);
        $this->ecommerceService->setWebpayOrderAsFailed($wooCommerceOrder, $webpayTransaction, $commitResponse);

        $this->handleAbortedTransaction(
            'wc_transbank_webpay_plus_transaction_failed',
            TbkConstants::TRANSACTION_STATUS_FAILED,
            self::WEBPAY_FAILED_FLOW_MESSAGE,
            $webpayTransaction,
            $wooCommerceOrder,
            $commitResponse
        );
    }

    /**
     * Handles the case when the transaction is already processed.
     *
     * @param string $token The transaction token.
     *
     * @return void
     */
    protected function handleTransactionAlreadyProcessed(string $token): void
    {
        $this->logInfo("Transacción ya se encontraba procesada. Token: {$token}");

        $webpayTransaction = $this->transactionRepository->findFirstByToken($token);
        $status = $webpayTransaction->status;
        $errorCode = self::WEBPAY_EXCEPTION_FLOW_MESSAGE;

        $this->logInfo("Estado de la transacción: {$status}");

        if ($status == TbkConstants::TRANSACTION_STATUS_APPROVED) {
            $wooCommerceOrder = $this->ecommerceService->getOrderById($webpayTransaction->order_id);
            $this->redirect($wooCommerceOrder->get_checkout_order_received_url());
            return;
        }

        if ($status == TbkConstants::TRANSACTION_STATUS_FAILED) {
            $errorCode = self::WEBPAY_FAILED_FLOW_MESSAGE;
        }

        if ($status == TbkConstants::TRANSACTION_STATUS_ABORTED_BY_USER) {
            $errorCode = self::WEBPAY_CANCELED_BY_USER_FLOW_MESSAGE;
        }

        if ($status == TbkConstants::TRANSACTION_STATUS_TIMEOUT) {
            $errorCode = self::WEBPAY_TIMEOUT_FLOW_MESSAGE;
        }

        if ($status == TbkConstants::TRANSACTION_STATUS_ERROR) {
            $errorCode = self::WEBPAY_ERROR_FLOW_MESSAGE;
        }

        $this->setPaymentErrorPage($errorCode);
    }


    /**
     * Handles the case when the transaction is aborted.
     *
     * @return void
     */
    protected function handleAbortedTransaction(
        $action,
        $status,
        $errorCode,
        $webpayTransaction,
        $wooCommerceOrder = null,
        $response = null
    ): void {
        $this->logInfo(
            "Error al procesar transacción por Transbank. Token: {$webpayTransaction->token}"
        );

        $data = ['status' => $status];

        if (!is_null($response)) {
            $data['transbank_response'] = json_encode($response);
        }

        $this->transactionRepository->update($webpayTransaction->id,$data);

        $this->doAction(
            $action,
            $wooCommerceOrder,
            $webpayTransaction,
            $response);

        $this->setPaymentErrorPage($errorCode);
    }
    

    protected function setPaymentErrorPage($errorCode){
        $this->doAction('transbank_webpay_plus_unexpected_error');
        $this->logError(self::ERROR_MESSAGES[$errorCode]);
        $this->redirect($this->getCheckoutUrlWithError( $errorCode));
    }

    protected function redirect($url)
    {
        wp_redirect($url);
    }

    protected function getCheckoutUrlWithError($errorCode)
    {
        return add_query_arg(['transbank_status' => $errorCode], wc_get_checkout_url());
    }

    protected function doAction($name, $wooCommerceOrder = null, $webpayTransaction = null, $response = null)
    {
        do_action($name, [
            'order' => $wooCommerceOrder ? $wooCommerceOrder->get_data() : null,
            'transbankTransaction' => $webpayTransaction,
            'transbankResponse' => $response
        ]);
    }

    /**
     * Checks if the transaction is already processed by the status.
     *
     * @param string $status The transaction status.
     *
     * @return bool
     */
    protected function checkTransactionIsAlreadyProcessedByStatus(string $status): bool
    {
        return $status != TbkConstants::TRANSACTION_STATUS_INITIALIZED;
    }

}


