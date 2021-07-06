<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<br />
<h2>Detalles del pago</h2>
<table class="shop_table order_details">
    <tfoot>
    <tr>
        <th scope="row">Respuesta de la Transacción:</th>
        <td><span class="RT"><?php echo $responseTitle; ?></span></td>

    </tr>
    <tr>
        <th scope="row">Orden de Compra:</th>
        <td><span class="RT"><?php echo $finalResponse->buyOrder; ?></span></td>
    </tr>
    <tr>
        <th scope="row">Código de autorización:</th>
        <td><span class="CA"><?php echo $firstTransaction->authorizationCode ?? '-'; ?></span></td>
    </tr>
    <tr>
        <th scope="row">Fecha transacción:</th>
        <td><span class="FC"><?php echo $dateAccepted->format('d-m-Y'); ?></span></td>
    </tr>
    <tr>
        <th scope="row"> Hora transacción:</th>
        <td><span class="FT"><?php echo $dateAccepted->format('H:i:s'); ?></span></td>
    </tr>
    <tr>
        <th scope="row">Número de tarjeta:</th>
        <td><span class="TC">**** **** **** <?php echo $finalResponse->cardNumber; ?></span></td>
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
        <td><span class="amount">$<?php echo number_format($firstTransaction->amount, 0, ',', '.'); ?></span></td>
    </tr>
    <tr>
        <th scope="row">Número de cuotas:</th>
        <td><span class="NC"><?php echo $firstTransaction->installmentsNumber ?? '-'; ?></span></td>
    </tr>
    <?php if ($firstTransaction->installmentsAmount) { ?>
    <tr>
        <th scope="row">Monto de cada cuota:</th>
        <td><span class="NC"><?php echo $firstTransaction->installmentsAmount ?? '-'; ?></span></td>
    </tr>
    <?php } ?>
    <tr>
        <th scope="row">C&oacute;digo de respuesta de la transacción:</th>
        <td><span class="CT"><?php echo $firstTransaction->responseCode; ?></span></td>
    </tr>
    </tfoot>
</table><br/>
