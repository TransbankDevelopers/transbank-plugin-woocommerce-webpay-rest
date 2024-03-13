<?php
if (!defined('ABSPATH')) {
    return;
}
?>
<h2 id="payment_details">Detalles del pago</h2>
<table class="shop_table order_details" aria-describedby="payment_details">
    <tfoot>
    <tr>
        <th scope="row">Orden de Compra:</th>
        <td><span class="RT"><?php echo $buyOrder; ?></span></td>
    </tr>
    <tr>
        <th scope="row">Código de autorización:</th>
        <td><span class="CA"><?php echo $authorizationCode; ?></span></td>
    </tr>
    <tr>
        <th scope="row">Fecha transacción:</th>
        <td><span class="FC"><?php echo $transactionDate; ?></span></td>
    </tr>
    <tr>
        <th scope="row"> Hora transacción:</th>
        <td><span class="FT"><?php echo $transactionTime; ?></span></td>
    </tr>
    <tr>
        <th scope="row">Número de tarjeta:</th>
        <td><span class="TC"><?php echo $cardNumber; ?></span></td>
    </tr>
    <tr>
        <th scope="row">Tipo de pago:</th>
        <td><span class="TP"><?php echo $paymentType; ?></span></td>
    </tr>
    <tr>
        <th scope="row">Tipo de cuota:</th>
        <td><span class="TC"><?php echo $installmentType; ?></span></td>
    </tr>
    <tr>
        <th scope="row">Monto compra:</th>
        <td><span class="amount"><?php echo $amount; ?></span></td>
    </tr>
    <tr>
        <th scope="row">Número de cuotas:</th>
        <td><span class="NC"><?php echo $installmentNumber; ?></span></td>
    </tr>
    <?php if ($installmentNumber > 0) { ?>
    <tr>
        <th scope="row">Monto de cada cuota:</th>
        <td><span class="NC"><?php echo $installmentAmount; ?></span></td>
    </tr>
    <?php } ?>
    </tfoot>
</table><br/>
