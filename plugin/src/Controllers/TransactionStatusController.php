<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Transbank\Plugin\Helpers\ErrorUtil;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkResponseUtil;
use Transbank\Plugin\Helpers\TbkConstants;
use Transbank\WooCommerce\WebpayRest\Services\TransactionService;

class TransactionStatusController
{
    const HTTP_OK = 200;
    const HTTP_UNPROCESSABLE_ENTITY = 422;
    const NO_TRANSACTION_ERROR_MESSAGE = 'No hay transacciones webpay para esta orden.';
    const BUY_ORDER_MISMATCH_ERROR_MESSAGE = 'El buy_order enviado y el buy_order de la transacción no coinciden.';
    const TOKEN_MISMATCH_ERROR_MESSAGE = 'El token enviado y el token de la transacción no coinciden.';

    /**
     * Log instance.
     * @var \Transbank\Plugin\Helpers\PluginLogger
     */
    private $logger;
    protected TransactionService $transactionService;

    /**
     * Controller for status requests.
     */
    public function __construct()
    {
        $this->logger = TbkFactory::createLogger();
        $this->transactionService = TbkFactory::createTransactionService();
    }

    public function getStatus(): void
    {
        $response = [
            'body' => [
                'message' => ErrorUtil::DEFAULT_STATUS_ERROR_MESSAGE
            ]
        ];

        $this->logger->logInfo('Obteniendo estado de la transacción.');

        // Check for nonce security
        $nonce = sanitize_text_field($_POST['nonce']);

        if (!wp_verify_nonce($nonce, 'my-ajax-nonce')) {
            $this->logger->logError($response['body']['message']);
            wp_send_json($response['body'], self::HTTP_UNPROCESSABLE_ENTITY);
            return;
        }

        $orderId = $this->getSecureInputValue('order_id');
        $buyOrder = $this->getSecureInputValue('buy_order');
        $token = $this->getSecureInputValue('token');

        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $params = ['orderId' => $orderId, 'buyOrder' => $buyOrder, 'token' => $token];

        $this->logger->logDebug("Request: method -> $requestMethod");
        $this->logger->logDebug('Request: payload', $params);

        try {
            $transaction = $this->transactionService->findFirstByOrderId($orderId);

            if (!$transaction) {
                $this->logger->logError(self::NO_TRANSACTION_ERROR_MESSAGE);
                $response['body']['message'] = self::NO_TRANSACTION_ERROR_MESSAGE;
                wp_send_json($response['body'], self::HTTP_UNPROCESSABLE_ENTITY);
                return;
            }

            $this->logger->logInfo('Transacción encontrada.');
            $response = $this->handleGetStatus($transaction, $buyOrder, $token);

            wp_send_json($response['body'], $response['code']);
        } catch (\Throwable $e) {
            $errorMessage = ErrorUtil::getStatusErrorMessage($e);
            $this->logger->logError($errorMessage);
            $response['body']['message'] = $errorMessage;
            wp_send_json($response['body'], self::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function handleGetStatus(object $transaction, string $buyOrder, string $token): array
    {
        if ($transaction->product == TbkConstants::TRANSACTION_WEBPAY_ONECLICK) {
            return $this->handleOneclickStatus($buyOrder, $transaction->buy_order);
        }

        return $this->handleWebpayStatus($token, $transaction->token);
    }

    private function handleOneclickStatus(
        string $requestBuyOrder,
        string $transactionBuyOrder
    ): array {
        if ($transactionBuyOrder !== $requestBuyOrder) {
            return [
                'body' => [
                    'message' => self::BUY_ORDER_MISMATCH_ERROR_MESSAGE
                ],
                'code' => self::HTTP_UNPROCESSABLE_ENTITY
            ];
        }

        $statusResponse = $this->getStatusForOneclickTransaction($transactionBuyOrder);
        $oneclickLogger = TbkFactory::createOneclickLogger();
        $formattedResponse = TbkResponseUtil::getOneclickStatusFormattedResponse($statusResponse);
        $oneclickLogger->logDebug('Estado de la transacción Oneclick', $formattedResponse);
        return [
            'body' => $formattedResponse,
            'code' => self::HTTP_OK
        ];
    }

    private function handleWebpayStatus(
        string $requestToken,
        string $transactionToken
    ): array {
        if ($transactionToken !== $requestToken) {
            return [
                'body' => [
                    'message' => self::TOKEN_MISMATCH_ERROR_MESSAGE
                ],
                'code' => self::HTTP_UNPROCESSABLE_ENTITY
            ];
        }

        $statusResponse = $this->getStatusForWebpayTransaction($transactionToken);
        $webpayLogger = TbkFactory::createWebpayPlusLogger();
        $formattedResponse = TbkResponseUtil::getWebpayStatusFormattedResponse($statusResponse);
        $webpayLogger->logDebug('Estado de la transacción Webpay Plus', $formattedResponse);
        return [
            'body' => $formattedResponse,
            'code' => self::HTTP_OK
        ];
    }

    private function getStatusForWebpayTransaction(string $token)
    {
        $service = TbkFactory::createWebpayService();
        return $service->status($token);
    }

    private function getStatusForOneclickTransaction(string $buyOrder)
    {
        $service = TbkFactory::createOneclickAuthorizationService();
        return $service->status($buyOrder);
    }

    private function getSecureInputValue(string $varName): string
    {
        $tmpValue = filter_input(INPUT_POST, $varName, FILTER_DEFAULT);
        return htmlspecialchars($tmpValue, ENT_QUOTES, 'UTF-8');
    }
}
