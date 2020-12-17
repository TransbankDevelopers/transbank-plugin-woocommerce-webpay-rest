<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<hr>

<table class="form-table">
    <?php $this->generate_settings_html(); ?>
</table>

<div id="my-content-id" style="display:none;overflow-y: scroll; max-height: 50vh; w">
    <h2>¡Excelente!</h2>
    <img style="float: right; width: 180px; padding: 20px; display: block" src="<?php echo plugins_url('/libwebpay/images/webpay-new.png', dirname(__DIR__)); ?>" alt="">
    <div>
        <p>Ahora que ya tienes el plugin instalado, tu sitio ya está  habilitado para que tus clientes puedan pagar usando Webpay Plus.
            Asegúrate de que tu tienda esté
            <a target="_blank" href="<?php echo admin_url('admin.php?page=wc-settings&tab=general'); ?>">configurada para aceptar pesos chilenos</a>

        </p>
    </div>


    <p>Notarás que el plugin viene configurado en modo Integración, por lo que opera en un ambiente de pruebas, con dinero y tarjetas de prueba.</p>

    <h3>¿Que hago ahora?</h3>
    <p>Verifica que todo funcione correctamente. Realiza algunas compras con tarjetas de crédito y de débito, además de probar con transacciones aprobadas y rechazadas.
        <br>Si todo funciona correctamente, solicita tu llave secreta (Api Key Secret). A continuación te explicamos como.  </p>


    <h3>Operar en el ambiente de producción</h3>

    <p>Para operar en producción - con tarjetas y dinero real - debes seguir este proceso de validación para obtener tu propia llave secreta
    (API Key) y código de comercio. Revisa
        <a target="_blank" href="https://transbankdevelopers.cl/documentacion/como_empezar#puesta-en-produccion">las instrucciones de cómo pasar a producción</a>

    <h3>Documentación</h3>
    <p>Encuentra más detalles y funcionalidades en la <a target="_blank" href="http://transbankdevelopers.cl/plugin/woocommerce/">documentación oficial del plugin</a></p>
<!--    <iframe width="100%" height="315" src="https://www.youtube.com/embed/AB9eh7BTJUE" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>-->

</div>

<?php if($environment === 'TEST') { ?>
<div class="transbank-rest-credentials">
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

<a href="#" id="show-welcome-message" style="padding-top: 10px" class="">Ver mensaje de bienvenida nuevamente</a>


<script>
    (function($) {
        <?php if (!$showedWelcome) { ?>
            openWelcomeMessageTransbankWebpayRest();
        <?php } ?>
        $('#show-welcome-message').click(function() {
            openWelcomeMessageTransbankWebpayRest();
        })
        //window.tb_show('TbkRestModalWelcome', "#TB_inline?&width=600&height=550&inlineId=my-content-id", null);
        function openWelcomeMessageTransbankWebpayRest() {
            let content = $('#my-content-id').clone();
            content.show();
            swal({
                content: content[0],
            });
        }
    })(jQuery);

</script>
