<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use DateTime;
use DateTimeZone;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkResponseUtil;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\PaymentGateways\WC_Gateway_Transbank_Oneclick_Mall_REST;
use Transbank\WooCommerce\WebpayRest\PaymentGateways\WC_Gateway_Transbank_Webpay_Plus_REST;
use WC_Order;

class ThankYouPageController
{
    private $logger;
    public function __construct() {
        $logger = TbkFactory::createLogger();
        $this->logger = $logger;
    }

    public function show($orderId)
    {
        $woocommerceOrder = new WC_Order($orderId);

        if(!$this->isValidPaymentGateway($woocommerceOrder->get_payment_method())) {
            $this->logger->logDebug(
                "La pasarela de pago no es valida, se ha pagado con {$woocommerceOrder->get_payment_method_title()}"
            );
            return;
        }

        $webpayTransaction = Transaction::getApprovedByOrderId($orderId);

        if(is_null($webpayTransaction)) {
            wc_print_notice('<strong>Transacción fallida</strong>. Puedes volver a intentar el pago', 'error');
            return;
        }

        // Transacción aprobada
        wc_print_notice(__('Transacción aprobada', 'transbank_wc_plugin'), 'success');
        $finalResponse = json_decode($webpayTransaction->transbank_response);

        if ($webpayTransaction->product == Transaction::PRODUCT_WEBPAY_ONECLICK) {
            $firstTransaction = $finalResponse->details[0] ?? null;
            $responseCode = $firstTransaction->responseCode ?? null;
            $status = $firstTransaction->status ?? null;
            $paymentTypeCode = $firstTransaction->paymentTypeCode;
            $responseTitle = ($responseCode === 0 && $status === 'AUTHORIZED') ? 'Transacción Aprobada' : 'Transacción Rechazada';
            $dateAccepted = new DateTime($finalResponse->transactionDate ?? null, new DateTimeZone('UTC'));
            $dateAccepted->setTimeZone(new DateTimeZone(wc_timezone_string()));
            $paymentType = TbkResponseUtil::getPaymentType($paymentTypeCode);
            $installmentType = TbkResponseUtil::getInstallmentType($paymentTypeCode);
            require_once __DIR__.'/../../templates/public/order/order-summary-oneclick.php';

            return;
        }
        $data = (new ResponseController([]))->getTransactionDetails($finalResponse);
        list($authorizationCode, $amount, $sharesNumber, $transactionResponse, $installmentType, $date_accepted, $sharesAmount, $paymentType) = $data;
        require_once __DIR__.'/../../templates/public/order/order-summary.php';
    }

    private function isValidPaymentGateway($paymentMethod): bool {
        $paymentGateways = [
            WC_Gateway_Transbank_Webpay_Plus_REST::ID,
            WC_Gateway_Transbank_Oneclick_Mall_REST::ID
        ];
        return in_array($paymentMethod, $paymentGateways);
    }
}
