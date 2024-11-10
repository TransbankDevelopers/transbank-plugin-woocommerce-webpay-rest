<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkResponseUtil;

class TransactionStatusController
{
    const HTTP_OK = 200;
    const HTTP_UNPROCESSABLE_ENTITY = 422;
    const DEFAULT_ERROR_MESSAGE = 'No se pudo obtener el estado de la transacción';
    const NO_TRANSACTION_ERROR_MESSAGE = 'No hay transacciones webpay aprobadas para esta orden';
    const BUY_ORDER_MISMATCH_ERROR_MESSAGE = 'El buy_order enviado y el buy_order de la transacción no coinciden';
    const TOKEN_MISMATCH_ERROR_MESSAGE = 'El token enviado y el token de la transacción no coinciden';
    public function getStatus(): void
    {
        $response = [
            'body' => [
                'message' => self::DEFAULT_ERROR_MESSAGE
            ]
        ];
        // Check for nonce security
        $nonce = sanitize_text_field($_POST['nonce']);

        if (!wp_verify_nonce($nonce, 'my-ajax-nonce')) {
            wp_send_json($response['body'], self::HTTP_UNPROCESSABLE_ENTITY);
            return;
        }

        $orderId = $this->getSecureInputValue('order_id');
        $buyOrder = $this->getSecureInputValue('buy_order');
        $token = $this->getSecureInputValue('token');

        try {
            $transaction = Transaction::getApprovedByOrderId($orderId);

            if (!$transaction) {
                $response['body'] = self::NO_TRANSACTION_ERROR_MESSAGE;
                wp_send_json($response['body'], self::HTTP_UNPROCESSABLE_ENTITY);
                return;
            }

            $response = $this->handleGetStatus($transaction, $orderId, $buyOrder, $token);

            wp_send_json($response['body'], $response['code']);
        } catch (\Exception $e) {
            wp_send_json([
                'message' => $e->getMessage(),
            ], 422);
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

        return [
            'body' => $this->getStatusForOneclickTransaction($orderId, $transactionBuyOrder),
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

        return [
            'body' => $this->getStatusForWebpayTransaction($orderId, $transactionToken),
            'code' => self::HTTP_OK
        ];
    }

    private function getStatusForWebpayTransaction(string $orderId, string $token)
    {
        $webpayTransbankSDK = TbkFactory::createWebpayplusTransbankSdk();
        $resp = $webpayTransbankSDK->status($orderId, $token);
        $formattedDate = TbkResponseUtil::transactionDateToLocalDate($resp->getTransactionDate());
        $modifiedResponse = clone $resp;
        $modifiedResponse->setTransactionDate($formattedDate);

        return [
            'product' => Transaction::PRODUCT_WEBPAY_PLUS,
            'status'  => $modifiedResponse,
            'raw'     => $resp,
        ];
    }

    private function getStatusForOneclickTransaction(string $orderId, string $buyOrder)
    {
        $oneclickTransbankSDK = TbkFactory::createOneclickTransbankSdk();
        $status = $oneclickTransbankSDK->status($orderId, $buyOrder);
        $statusArray = json_decode(json_encode($status), true);
        $firstDetail = json_decode(json_encode($status->getDetails()[0]), true);

        $response = array_merge($statusArray, $firstDetail);
        $formattedDate = TbkResponseUtil::transactionDateToLocalDate($status->getTransactionDate());
        $response['transactionDate'] = $formattedDate;
        unset($response['details']);

        return [
            'product' => Transaction::PRODUCT_WEBPAY_ONECLICK,
            'status'  => $response,
            'raw'     => $status,
        ];
    }

    private function getSecureInputValue(string $varName): string
    {
        $tmpValue = filter_input(INPUT_POST, $varName, FILTER_DEFAULT);
        return htmlspecialchars($tmpValue, ENT_QUOTES, 'UTF-8');
    }
}
