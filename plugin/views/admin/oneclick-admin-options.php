<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<hr>

<div style="clear: both">
    <a target="_blank" href="https://contrata.transbankdevelopers.cl/?utm_source=woocommerce_plugin">
        <img style="border-radius: 10px; width: 800px; display: block" src="<?php echo plugins_url('/images/oneclick-banner.jpg', dirname(__DIR__)); ?>" alt="">
    </a>
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

