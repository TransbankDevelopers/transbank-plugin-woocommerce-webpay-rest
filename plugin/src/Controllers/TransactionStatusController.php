<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Transbank\Webpay\WebpayPlus\Exceptions\TransactionStatusException;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\PaymentGateways\WC_Gateway_Transbank_Oneclick_Mall_REST;
use Transbank\WooCommerce\WebpayRest\TransbankSdkWebpayRest;

class TransactionStatusController
{
    public static function status()
    {
        // Check for nonce security
        $nonce = sanitize_text_field($_POST['nonce']);

        if (!wp_verify_nonce($nonce, 'my-ajax-nonce')) {
            exit('Busted!');
        }

        $orderId = filter_input(INPUT_POST, 'order_id', FILTER_SANITIZE_NUMBER_INT);
        $buyOrder = filter_input(INPUT_POST, 'buy_order', FILTER_SANITIZE_NUMBER_INT);
        $token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_STRING);

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

            $oneclick = new WC_Gateway_Transbank_Oneclick_Mall_REST();
            $status = $oneclick->getStatus($buyOrder);
            $statusArray = json_decode(json_encode($oneclick->getStatus($buyOrder)), true);
            $firstDetail = json_decode(json_encode($status->getDetails()[0]), true);

            $response = array_merge($statusArray, $firstDetail);
            unset($response['detail']);
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

        $sdk = new TransbankSdkWebpayRest();

        try {
            wp_send_json([
                'product' => $transaction->product,
                'status'  => $sdk->status($transaction->token),
                'raw'     => $sdk->status($transaction->token),
            ]);
        } catch (TransactionStatusException $e) {
            wp_send_json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
