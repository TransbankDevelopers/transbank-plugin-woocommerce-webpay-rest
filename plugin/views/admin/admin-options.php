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

    <h3>Documentación</h3>
    <p>Encuentra más detalles y funcionalidades en la <a target="_blank" href="http://transbankdevelopers.cl/plugin/woocommerce/">documentación oficial del plugin</a></p>
    <!--<iframe width="100%" height="315" src="https://www.youtube.com/embed/AB9eh7BTJUE" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>-->

</div>

<?php if ($environment === 'TEST') { ?>
<div class="transbank-rest-credentials">
    <h3>¿Quieres operar en producción?</h3>
    Para operar en el ambiente productivo, con dinero real, debes tener tu <strong>código de comercio</strong> y tu <strong>Api Key</strong>.

    <h4>Código de comercio</h4>
    Si no lo tienes, puedes solicitarlo en <a href="https://public.transbank.cl">el sitio web de Transbank</a>.

    <h4>Tu Api Key</h4>
    Si ya tienes tu código de comercio, lo único que te faltaría es tu Api Key. Para obtenerla, debes completar el siguiente formulario:
    <br>
    <a href="https://form.typeform.com/to/fZqOJyFZ?typeform-medium=embed-snippet" style="margin-top: 5px; display: inline-block;clear: both" data-mode="popup" class="typeform-share link button-primary" data-size="100" data-submit-close-delay="25">Comenzar proceso de validación</a>


    <br><br><br>

    Si quieres, puedes revisar <a target="_blank" href="https://transbankdevelopers.cl/documentacion/como_empezar#puesta-en-produccion">las instrucciones detalladas de cómo pasar a producción</a>

</div>
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
<script> (function() { var qs,js,q,s,d=document, gi=d.getElementById, ce=d.createElement, gt=d.getElementsByTagName, id="typef_orm_share", b="https://embed.typeform.com/"; if(!gi.call(d,id)){ js=ce.call(d,"script"); js.id=id; js.src=b+"embed.js"; q=gt.call(d,"script")[0]; q.parentNode.insertBefore(js,q) } })() </script>
