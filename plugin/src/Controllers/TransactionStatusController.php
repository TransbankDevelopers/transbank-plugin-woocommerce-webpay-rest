<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Transbank\WooCommerce\WebpayRest\Helpers\ErrorUtil;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkResponseUtil;

class TransactionStatusController
{
    const HTTP_OK = 200;
    const HTTP_UNPROCESSABLE_ENTITY = 422;
    const NO_TRANSACTION_ERROR_MESSAGE = 'No hay transacciones webpay aprobadas para esta orden';
    const BUY_ORDER_MISMATCH_ERROR_MESSAGE = 'El buy_order enviado y el buy_order de la transacción no coinciden';
    const TOKEN_MISMATCH_ERROR_MESSAGE = 'El token enviado y el token de la transacción no coinciden';
    const EXCEPTION_MESSAGE = 'Ha ocurrido un error al obtener el estado de la transacción.';

    /**
     * Log instance.
     * @var \Transbank\Plugin\Helpers\PluginLogger
     */
    private $logger;

    /**
     * Controller for status requests.
     */
    public function __construct()
    {
        $this->logger = TbkFactory::createLogger();
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
        $this->logger->logDebug('Request: payload -> ' . json_encode($params));

        try {
            $transaction = Transaction::getApprovedByOrderId($orderId);

            if (!$transaction) {
                $this->logger->logError(self::NO_TRANSACTION_ERROR_MESSAGE);
                $response['body']['message'] = self::NO_TRANSACTION_ERROR_MESSAGE;
                $response['body'] = self::NO_TRANSACTION_ERROR_MESSAGE;
                wp_send_json($response['body'], self::HTTP_UNPROCESSABLE_ENTITY);
                return;
            }

            $this->logger->logInfo('Transacción encontrada.');
            $response = $this->handleGetStatus($transaction, $orderId, $buyOrder, $token);

            wp_send_json($response['body'], $response['code']);
        } catch (\Throwable $e) {
            $this->logger->logError($e->getMessage());
            $response['body']['message'] = $e->getMessage();
            wp_send_json($response['body'], self::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function handleGetStatus(object $transaction, string $orderId, string $buyOrder, string $token): array
    {
        if ($transaction->product == Transaction::PRODUCT_WEBPAY_ONECLICK) {
            return $this->handleOneclickStatus($orderId, $buyOrder, $transaction->buy_order);
        }

        return $this->handleWebpayStatus($orderId, $token, $transaction->token);
    }

    private function handleOneclickStatus(
        string $orderId,
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

        $statusResponse = $this->getStatusForOneclickTransaction($orderId, $transactionBuyOrder);

        return [
            'body' => TbkResponseUtil::getOneclickStatusFormattedResponse($statusResponse),
            'code' => self::HTTP_OK
        ];
    }

    private function handleWebpayStatus(
        string $orderId,
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

        $statusResponse = $this->getStatusForWebpayTransaction($orderId, $transactionToken);

        return [
            'body' => TbkResponseUtil::getWebpayStatusFormattedResponse($statusResponse),
            'code' => self::HTTP_OK
        ];
    }

    private function getStatusForWebpayTransaction(string $orderId, string $token)
    {
        $webpayTransbankSDK = TbkFactory::createWebpayplusTransbankSdk();
        return $webpayTransbankSDK->status($orderId, $token);
    }

    private function getStatusForOneclickTransaction(string $orderId, string $buyOrder)
    {
        $oneclickTransbankSDK = TbkFactory::createOneclickTransbankSdk();
        return $oneclickTransbankSDK->status($orderId, $buyOrder);
    }

    private function getSecureInputValue(string $varName): string
    {
        $tmpValue = filter_input(INPUT_POST, $varName, FILTER_DEFAULT);
        return htmlspecialchars($tmpValue, ENT_QUOTES, 'UTF-8');
    }
}
