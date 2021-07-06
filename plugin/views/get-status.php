<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!$transaction) {
    echo 'No hay transacciones webpay aprobadas para esta orden';

    return;
}
?>

<a class="button action get-transaction-status" data-order-id="<?php echo $order->get_id(); ?>" data-buy-order="<?php echo $transaction->buy_order; ?>" data-token="<?php echo $transaction->token; ?>" href="">Consultar estado de la transacción</a>

<p>Esta es la respuesta del API (solo disponible por 7 días desde la fecha de transacción)</p>

 <table class="transaction-status-response" cellspacing="0" cellpadding="0" style="display: none">
     <tr>
         <th>Producto</th>
     </tr>
     <tr>
         <td class="status-product"></td>
     </tr>
     <tr>
         <th>Fecha contable:</th>
     </tr>
     <tr>
         <td class="status-accountingDate"></td>
     </tr>
     <tr>
         <th>Fecha de transacción:</th>
     </tr>
     <tr>
         <td class="status-transactionDate"></td>
     </tr>
     <tr>
         <th>Estado:</th>
     </tr>
     <tr>
         <td class="status-status"></td>
     </tr>
     <tr>
         <th>Monto de la transacción:</th>
     </tr>
     <tr>
         <td class="status-amount"></td>
     </tr>
     <tr>
         <th>Balance:</th>
     </tr>
     <tr>
         <td class="status-balance"></td>
     </tr>
     <tr>
         <th>Código de autorización:</th>
     </tr>
     <tr>
         <td class="status-authorizationCode"></td>
     </tr>
     <tr>
         <th>VCI:</th>
     </tr>
     <tr>
         <td class="status-vci"></td>
     </tr>
     <tr>
         <th>Orden de compra:</th>
     </tr>
     <tr>
         <td class="status-buyOrder"></td>
     </tr>
     <tr>
         <th>ID Sesión:</th>
     </tr>
     <tr>
         <td class="status-sessionId"></td>
     </tr>
     <tr>
         <th>Tipo de pago:</th>
     </tr>
     <tr>
         <td class="status-paymentTypeCode"></td>
     </tr>
     <tr>
         <th>Código de respuesta:</th>
     </tr>
     <tr>
         <td class="status-responseCode"></td>
     </tr>
     <tr>
         <th>Número de cuotas:</th>
     </tr>
     <tr>
         <td class="status-installmentsAmount"></td>
     </tr>
     <tr>
         <th>Monto de cada cuota:</th>
     </tr>
     <tr>
         <td class="status-installmentsNumber"></td>
     </tr>
     <tr>
         <th>Respuesta completa:</th>
     </tr>
     <tr>
         <td class="status-raw"></td>
     </tr>

 </table>

