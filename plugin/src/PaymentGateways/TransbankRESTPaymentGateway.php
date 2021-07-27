<?php

namespace Transbank\WooCommerce\WebpayRest\PaymentGateways;

use WC_Order;

trait TransbankRESTPaymentGateway
{
    /**
     * @param  $response
     * @param WC_Order $order
     * @param $amount
     * @param $jsonResponse
     */
    public function addRefundOrderNote($response, WC_Order $order, $amount, $jsonResponse)
    {
        $type = $response->getType() === 'REVERSED' ? 'Reversa' : 'Anulación';
        $amountFormatted = '$'.number_format($amount, 0, ',', '.');
        $balanceFormatted = '$'.number_format($response->getBalance(), 0, ',', '.');
        $note = "<div class='transbank_response_note'>
          <p><h3>Reembolso exitoso</h3></p><br />

          <strong>Medio de pago:</strong> Webpay Oneclick<br />
          <strong>Tipo:</strong> {$type}<br />
          <strong>Monto devuelto:</strong> {$amountFormatted}<br />
          <strong>Balance:</strong> {$balanceFormatted}<br /><br /><br />

          <strong>Respuesta de anulación:</strong> <br />
          <pre style='overflow-x: scroll'>".$jsonResponse.'</pre>
        </div>';

        $order->add_order_note($note);
    }
}
