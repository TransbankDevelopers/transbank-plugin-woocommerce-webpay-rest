<?php
if (!defined('ABSPATH')) {
    return;
}
if (!$transaction) {
    echo 'No hay transacciones webpay aprobadas para esta orden';

    return;
}
?>

<a class="button action get-transaction-status" data-order-id="<?php echo $order->get_id(); ?>" data-buy-order="<?php echo $transaction->buy_order; ?>" data-token="<?php echo $transaction->token; ?>" href="">Consultar estado de la transacción</a>

<p>Esta es la respuesta del API (solo disponible por 7 días desde la fecha de transacción)</p>

<div class="transaction-status-response" id="transaction_status_admin" style="display: none">
    <dl class="transaction-status-response">
        <dt>Producto:</dt>
        <dd class="status-product"></dd>
    </dl>
    <dl class="transaction-status-response">
        <dt>Fecha contable:</dt>
        <dd class="status-accountingDate"></dd>
    </dl>
    <dl class="transaction-status-response">
        <dt>Fecha de transacción:</dt>
        <dd class="status-transactionDate"></dd>
    </dl>
    <dl class="transaction-status-response">
        <dt>Estado:</dt>
        <dd class="status-status"></dd>
    </dl>
    <dl class="transaction-status-response">
        <dt>Monto de la transacción:</dt>
        <dd class="status-amount"></dd>
    </dl>
    <dl class="transaction-status-response">
        <dt>Balance:</dt>
        <dd class="status-balance"></dd>
    </dl>
    <dl class="transaction-status-response">
        <dt>Código de autorización:</dt>
        <dd class="status-authorizationCode"></dd>
    </dl>
    <dl class="transaction-status-response tbk-hide" id="tbk_wpp_vci">
        <dt>VCI:</dt>
        <dd class="status-vci"></dd>
    </dl>
    <dl class="transaction-status-response">
        <dt>Orden de compra:</dt>
        <dd class="status-buyOrder"></dd>
    </dl>
    <dl class="transaction-status-response tbk-hide" id="tbk_wpoc_commerce_code">
        <dt>Código de comercio:</dt>
        <dd class="status-commerceCode"></dd>
    </dl>
    <dl class="transaction-status-response tbk-hide" id="tbk_wpp_session_id">
        <dt>ID Sesión:</dt>
        <dd class="status-sessionId"></dd>
    </dl>
    <dl class="transaction-status-response">
        <dt>Tipo de pago:</dt>
        <dd class="status-paymentTypeCode"></dd>
    </dl>
    <dl class="transaction-status-response">
        <dt>Código de respuesta:</dt>
        <dd class="status-responseCode"></dd>
    </dl>
    <dl class="transaction-status-response">
        <dt>Número de cuotas:</dt>
        <dd class="status-installmentsNumber"></dd>
    </dl>
    <dl class="transaction-status-response">
        <dt>Monto de cada cuota:</dt>
        <dd class="status-installmentsAmount"></dd>
    </dl>
    <dl class="transaction-status-response">
        <dt >Respuesta Completa:</dt>
        <dd class="status-raw"></dd>
    </dl>
</div>

<div class="error-transaction-status-response" style="display: none">
    <div>Error consultando estado de la transacción</div>
    <div class="error-status-raw"></div>
</div>
