<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkResponseUtil;

class TransactionStatusController
{
    public function getStatus()
    {
        // Check for nonce security
        $nonce = sanitize_text_field($_POST['nonce']);

        if (!wp_verify_nonce($nonce, 'my-ajax-nonce')) {
            return;
        }

        $orderId = filter_input(INPUT_POST, 'order_id', FILTER_DEFAULT);
        $orderId = htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8');
        $buyOrder = filter_input(INPUT_POST, 'buy_order', FILTER_DEFAULT);
        $buyOrder = htmlspecialchars($buyOrder, ENT_QUOTES, 'UTF-8');
        $token = filter_input(INPUT_POST, 'token', FILTER_DEFAULT);
        $token = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');

        $transaction = Transaction::getApprovedByOrderId($orderId);
        if (!$transaction) {
            wp_send_json([
                'message' => 'No hay transacciones webpay aprobadas para esta orden',
            ], 401);
        }

        if ($transaction->product == Transaction::PRODUCT_WEBPAY_ONECLICK) {
            if ($transaction->buy_order !== $buyOrder) {
                wp_send_json([
                    'message' => 'El buy_order enviado y el buy_order de la transacción no coinciden',
                ], 401);
            }
            $oneclickTransbankSdk = TbkFactory::createOneclickTransbankSdk();
            $status = $oneclickTransbankSdk->status($orderId, $buyOrder);
            $statusArray = json_decode(json_encode($status), true);
            $firstDetail = json_decode(json_encode($status->getDetails()[0]), true);

            $response = array_merge($statusArray, $firstDetail);
            $formattedDate = TbkResponseUtil::transactionDateToLocalDate($status->getTransactionDate());
            $response['transactionDate'] = $formattedDate;
            unset($response['details']);
            wp_send_json([
                'product' => $transaction->product,
                'status'  => $response,
                'raw'     => $status,
            ]);

            return;
        }

        if ($transaction->token !== $token) {
            wp_send_json([
                'message' => 'El token enviado y el token de la transacción no coinciden',
            ], 401);
        }

        try {
            $webpayplusTransbankSdk = TbkFactory::createWebpayplusTransbankSdk();
            $resp = $webpayplusTransbankSdk->status($transaction->order_id, $transaction->token);
            $formattedDate = TbkResponseUtil::transactionDateToLocalDate($resp->getTransactionDate());
            $modifiedResponse = clone $resp;
            $modifiedResponse->setTransactionDate($formattedDate);
            wp_send_json([
                'product' => $transaction->product,
                'status'  => $modifiedResponse,
                'raw'     => $resp,
            ]);
        } catch (\Exception $e) {
            wp_send_json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

