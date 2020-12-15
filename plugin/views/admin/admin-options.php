

<h3><?php _e('Transbank Webpay', 'woocommerce'); ?></h3>
<p><?php _e('Transbank es la empresa líder en negocios de medio de pago seguros en Chile.'); ?></p>

<a class ="button " data-toggle="modal" href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_webpay_plus_rest&tbk_tab=healthcheck') ?>">Realizar diagnóstico</a>

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

    <iframe width="100%" height="315" src="https://www.youtube.com/embed/AB9eh7BTJUE" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

</div>


<a href="#" id="show-welcome-message" class="">Ver mensaje de bienvenida nuevamente</a>


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
