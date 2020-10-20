<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use DateTime;
use Transbank\WooCommerce\WebpayRest\TransbankWebpayOrders;
use Transbank\WooCommerce\WebpayRest\Helpers\SessionMessageHelper;
use WC_Gateway_Transbank_Webpay_Plus_REST;
use WC_Order;

class ThankYouPageController
{
    public function show($orderId)
    {
        $woocommerceOrder = new WC_Order($orderId);
        $transbank_data = new WC_Gateway_Transbank_Webpay_Plus_REST();
        if ($woocommerceOrder->get_payment_method_title() != $transbank_data->title) {
            return;
        }

        $webpayTransaction = TransbankWebpayOrders::getApprovedByOrderId($orderId);
        if ($webpayTransaction === null) {
            // TODO: Revisar porqué no se muestra el mensaje de abajo.
            // if (SessionMessageHelper::exists()){
            //     SessionMessageHelper::printMessage();
            //     return;
            // }
            wc_print_notice('Transacción <strong>fallida</strong>. Puedes volver a intentar el pago', 'error');
            return;
        }

        if ($webpayTransaction->status !== TransbankWebpayOrders::STATUS_APPROVED) {
            return wp_redirect($woocommerceOrder->get_cancel_order_url());
        }

        // Transacción aprobada
        wc_print_notice('Transacción aprobada', 'success');

        $finalResponse = json_decode($webpayTransaction->transbank_response);

        $paymentTypeCode = (isset($finalResponse->paymentTypeCode)) ? $finalResponse->paymentTypeCode : null;
        $responseCode = (isset($finalResponse->responseCode)) ? $finalResponse->responseCode : null;

        $paymentCodeResult = "Sin cuotas";
        if (isset($transbank_data->config)) {
            if (array_key_exists('VENTA_DESC', $transbank_data->config)) {
                if (array_key_exists($paymentTypeCode, $transbank_data->config['VENTA_DESC'])) {
                    $paymentCodeResult = $transbank_data->config['VENTA_DESC'][$paymentTypeCode];
                }
            }
        }

        if ($responseCode == 0) {
            $transactionResponse = __("Transacción Aprobada", 'transbank_webpay');
        } else {
            $transactionResponse = __("Transacción Rechazada", 'transbank_webpay');
        }

        $transactionDate = isset($finalResponse->transactionDate) ? $finalResponse->transactionDate : null;
        $date_accepted = new DateTime($transactionDate);


        if (in_array($paymentTypeCode, ["SI", "S2", "NC", "VC"])) {
            $installmentType = $paymentCodeResult;
        } else {
            $installmentType = __("Sin cuotas", 'transbank_webpay');
        }

        if ($paymentTypeCode == "VD") {
            $paymentType = __("Débito", 'transbank_webpay');
        } else {
            $paymentType = __("Crédito", 'transbank_webpay');
        }

        require(__DIR__ . '/../../views/order-summary.php');
    }
}
