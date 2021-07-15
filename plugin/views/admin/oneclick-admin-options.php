<?php
if (!defined('ABSPATH')) {
    exit;
}
$webpayPlus = new WC_Gateway_Transbank_Webpay_Plus_REST();
$webpayPlusEnvironment = $webpayPlus->get_option('webpay_rest_environment');
$webpayPlusCommerceCode = $webpayPlus->get_option('webpay_rest_commerce_code');
?>

<style>
    .woocommerce-save-button.button-primary {
        display: none;
    }
</style>

<div class="" style="display: flex; flex-wrap: wrap; margin-bottom: 10px">
    <div style="flex: 1; margin-bottom: 10px">
        <div style="margin-right: 10px; height: 100%; background: #fff; border-radius: 10px;">
            <a target="_blank" href="https://contrata.transbankdevelopers.cl/Oneclick/?wpcommerce=<?php echo $webpayPlusCommerceCode; ?>&wpenv=<?php echo $webpayPlusEnvironment; ?>&utm_source=woocommerce_plugin&utm_medium=banner&utm_campaign=contrata">
                <img style="border-radius: 10px; width: 400px; margin-right: 10px; display: block" src="<?php echo plugins_url('/images/oneclick-banner.jpg', dirname(__DIR__)); ?>" alt="">
            </a>
        </div>
    </div>


    <div style="flex: 2; margin-bottom: 10px; background: #fff; border-radius: 10px; padding: 20px; display:inline-block">
        <h3 style="margin-top: 0">Webpay Oneclick Mall</h3>
        <p>Webpay Oneclick le permite a tus usuarios inscribir su tarjeta en su cuenta de usuario, para que luego puedan
            comprar con un solo click. </p>
        <p>Este plugin funciona con Oneclick en modalidad MALL. Esto significa que tienes un <strong>código de comercio
                Mall</strong> que puede tener uno o varios códigos de comercio tienda o "hijos". Cuando un usuario inscribe su
            tarjeta, lo hace asociado su tarjeta al código de comercio Mall, pero cuando se realiza una transacción
            (autorización), esta es realizada por una (o más) tiendas del Mall. El dinero de esa transacción
            se pagará al código de comercio "tienda" y no al "mall". <br /><br />
            Este plugin funciona con una tienda Mall y una sola tienda. Todas las transacciones que se realicen usando este
            método de pago, se autorizarán asociadas al código de comercio tienda.<br></p>
    </div>

</div>

<div class="tbk-box">
    <table class="form-table">
        <?php $this->generate_settings_html(); ?>
    </table>
    <button name="save" class="button-primary woocommerce-save-button tbk-custom-save-button" type="submit" value="<?php _e('Guardar cambios', 'transbank_wc_plugin'); ?>"><?php _e('Guardar cambios', 'transbank_wc_plugin'); ?></button>
</div>


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
