<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkResponseUtil;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\PaymentGateways\WC_Gateway_Transbank_Oneclick_Mall_REST;
use Transbank\WooCommerce\WebpayRest\PaymentGateways\WC_Gateway_Transbank_Webpay_Plus_REST;
use Transbank\WooCommerce\WebpayRest\Utils\Template;
use WC_Order;

class ThankYouPageController
{
    public function show($orderId)
    {
        $woocommerceOrder = new WC_Order($orderId);

        if (!$this->isValidPaymentGateway($woocommerceOrder->get_payment_method())) {
            TbkFactory::createLogger()->logDebug(
                "La pasarela de pago no es válida, se ha pagado con {$woocommerceOrder->get_payment_method_title()}"
            );
            return;
        }

        $webpayTransaction = Transaction::getApprovedByOrderId($orderId);

        if (is_null($webpayTransaction)) {
            wc_print_notice('<strong>Transacción fallida</strong>. Puedes volver a intentar el pago', 'error');
            return;
        }

        wc_print_notice(__('Transacción aprobada', 'transbank_wc_plugin'), 'success');
        $transactionResponse = json_decode($webpayTransaction->transbank_response);

        $formattedResponse = [];
        if ($webpayTransaction->product == Transaction::PRODUCT_WEBPAY_ONECLICK) {
            $formattedResponse = TbkResponseUtil::getOneclickFormattedResponse($transactionResponse);
        } else {
            $formattedResponse = TbkResponseUtil::getWebpayFormattedResponse($transactionResponse);
        }

        (new Template())->render('public/order/order-summary.php', $formattedResponse);
    }

    private function isValidPaymentGateway($paymentMethod): bool
    {
        $paymentGateways = [
            WC_Gateway_Transbank_Webpay_Plus_REST::ID,
            WC_Gateway_Transbank_Oneclick_Mall_REST::ID
        ];
        return in_array($paymentMethod, $paymentGateways);
    }
}
