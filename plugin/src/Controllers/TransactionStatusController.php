<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Transbank\Webpay\WebpayPlus\Exceptions\TransactionStatusException;
use Transbank\WooCommerce\WebpayRest\TransbankSdkWebpayRest;
use Transbank\WooCommerce\WebpayRest\TransbankWebpayOrders;

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
        $token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_STRING);

        $transaction = TransbankWebpayOrders::getApprovedByOrderId($orderId);
        if (!$transaction) {
            wp_send_json([
                'message' => 'No hay transacciones webpay aprobadas para esta orden',
            ], 401);
        }

        if ($transaction->token !== $token) {
            wp_send_json([
                'message' => 'El token enviado y el token de la transacción no coinciden',
            ], 401);
        }

        $sdk = new TransbankSdkWebpayRest();

        try {
            wp_send_json($sdk->status($transaction->token));
        } catch (TransactionStatusException $e) {
            wp_send_json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
