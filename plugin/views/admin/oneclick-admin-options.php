<?php
if (!defined('ABSPATH')) {
    exit;
}
$webpayPlus = new WC_Gateway_Transbank_Webpay_Plus_REST();
$webpayPlusEnvironment = $webpayPlus->get_option('webpay_rest_environment');
$webpayPlusCommerceCode = $webpayPlus->get_option('webpay_rest_commerce_code');
?>

<hr>

<div style="clear: both">
    <a target="_blank" href="https://contrata.transbankdevelopers.cl/?wpcommerce=<?php echo $webpayPlusCommerceCode; ?>&wpenv=<?php echo $webpayPlusEnvironment; ?>&utm_source=woocommerce_plugin&utm_medium=banner&utm_campaign=contrata">
        <img style="border-radius: 10px; width: 800px; display: block" src="<?php echo plugins_url('/images/oneclick-banner.jpg', dirname(__DIR__)); ?>" alt="">
    </a>
</div>


<div style="max-width: 760px; margin: 20px 0; background: #fff; border-radius: 10px; padding: 20px; display:inline-block">
    <h3>Webpay Oneclick Mall</h3>
    <p>Este plugin funciona con Oneclick en modalidad MALL. Esto significa que tienes un <strong>código de comercio
    Mall</strong> que puede tener uno o varios códigos de comercio tienda o "hijos". Cuando un usuario inscribe su
    tarjeta, lo hace asociado su tarjeta al código de comercio Mall, pero cuando se realiza una transacción
    (autorización) esta es realizada por una (o más) tiendas del Mall. En el fondo, el dinero de esa transacción
    se pagará al código de comercio "tienda". <br /><br />
    Este plugin funciona con una tienda Mall y una sola tienda. Todas las transacciones que se realicen usando este
    método de pago, se autorizarán asociadas al código de comercio tienda. <br></p>
</div>


<table class="form-table">
    <?php $this->generate_settings_html(); ?>
</table>


<?php if ($environment === \Transbank\Webpay\Options::ENVIRONMENT_INTEGRATION) { ?>
    <div class="transbank-rest-credentials" style="margin-top: 20px">
        <h3>Credenciales de prueba</h3>
        En el ambiente de integración debes probar usando tarjetas de crédito y débito de prueba. <br>
        <a target="_blank" href="https://transbankdevelopers.cl/documentacion/como_empezar#ambiente-de-integracion">Encuentra las tarjeta de prueba acá </a>

        <p>
            Después de seleccionar el método de pago (en una compra de prueba), llegarás a una página de un Banco de prueba. Debes ingresar estas credenciales:
            <br>
            <strong>Rut:</strong> 11.111.111-1 <br>
            <strong>Clave:</strong> 123 <br>
        </p>
    </div>
<?php } ?>

