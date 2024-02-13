<?php

namespace Transbank\WooCommerce\WebpayRest\PaymentGateways;

use Transbank\WooCommerce\WebpayRest\Helpers\TbkResponseUtil;
use WC_Order;

trait TransbankRESTPaymentGateway
{
    /**
     * @param $response
     * @param WC_Order $order
     * @param $amount
     */
    public function addRefundOrderNote($response, WC_Order $order, $amount)
    {
        $type = $response->getType() === 'REVERSED' ? 'Reversa' : 'Anulación';
        $amountFormatted = '$'.number_format($amount, 0, ',', '.');
        $commonFields = "<div class='transbank_response_note'>
            <h3>Reembolso exitoso</h3>
            <strong>Tipo:</strong> {$type}
            <strong>Monto reembolso:</strong> {$amountFormatted}";

        if($type === 'Reversa') {
            $note = "{$commonFields}
            </div>";
        }
        else {
            $balanceFormatted = '$'.number_format($response->getBalance(), 0, ',', '.');
            $transactionDate = $response->getAuthorizationDate();
            $formattedDate = TbkResponseUtil::transactionDateToLocalDate($transactionDate);

            $note = "{$commonFields}
                <strong>Saldo:</strong> {$balanceFormatted}
                <strong>Fecha:</strong> {$formattedDate}
                <strong>Código autorización:</strong> {$response->getAuthorizationCode()}
                <strong>Código de respuesta:</strong> {$response->getResponseCode()}
            </div>";
        }

        $order->add_order_note($note);
    }
}
