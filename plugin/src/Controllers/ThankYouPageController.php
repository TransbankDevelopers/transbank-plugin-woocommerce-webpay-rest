<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Transbank\WooCommerce\WebpayRest\Helpers\SessionMessageHelper;
use Transbank\WooCommerce\WebpayRest\TransbankWebpayOrders;
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
        list($authorizationCode, $amount, $sharesNumber, $transactionResponse, $installmentType, $date_accepted, $sharesAmount, $paymentType) = (new ResponseController([]))->getTransactionDetails($finalResponse);

        require __DIR__.'/../../views/order-summary.php';
    }
}
