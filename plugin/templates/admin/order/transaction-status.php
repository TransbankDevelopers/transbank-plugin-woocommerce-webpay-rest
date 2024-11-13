<?php
if (!defined('ABSPATH')) {
    return;
}

$infoMessage = "El estado de la transacción está disponible solo por 7 días desde su creación.";

if (empty($viewData)) {
    $infoMessage = 'No hay transacciones Webpay asociadas a esta orden.';
}
?>

<div class="tbk-status-button">
    <a class="button tbk-button-primary get-transaction-status"
    data-order-id="<?php echo $viewData['orderId']; ?>"
    data-buy-order="<?php echo $viewData['buyOrder']; ?>"
    data-token="<?php echo $viewData['token']; ?>"
    href="#">
        Consultar Estado
    </a>
</div>

<div class="tbk-status tbk-status-info">
    <i class="fa fa-info-circle"></i>
    <p><?= $infoMessage ?></p>
</div>

<div id="transaction_status_admin">
</div>
