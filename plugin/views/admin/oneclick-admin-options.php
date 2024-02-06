<?php

use Transbank\WooCommerce\WebpayRest\Helpers\PluginInfoHelper;
if (!defined('ABSPATH')) {
    return;
}

$trackinfo = PluginInfoHelper::getInfo();
?>

<style>
    .woocommerce-save-button.button-primary {
        display: none;
    }
</style>

<div class="" style="display: flex; flex-wrap: wrap; margin-bottom: 10px">
    <div style="flex: 1; margin-bottom: 10px; margin-right: 10px;  background: #fff; border-radius: 10px; padding: 20px; display:inline-block">
        <h3 style="margin-top: 0">Webpay Oneclick Mall</h3>
        <p>Webpay Oneclick le permite a tus usuarios inscribir su tarjeta en su cuenta de usuario dentro de tu sitio, para que luego puedan
            comprar con un solo click. </p>
        <p style="margin: 0">Este plugin funciona en modalidad MALL. Esto significa que tienes un <strong>código de comercio
                mall</strong> y <strong>código de comercio tienda</strong>. Cuando un usuario inscribe su
            tarjeta, lo hace asociando su tarjeta al código de comercio Mall, pero cuando se realiza una transacción, el dinero de esa transacción
            se pagará al código de comercio "tienda" y no al "mall". <br /><br />
    </div>
    <div style="flex: 1; margin-bottom: 10px; background: #fff; border-radius: 10px; overflow: hidden; display:inline-block">
        <iframe width="100%" height="100%" src="https://www.youtube.com/embed/aVElZf5xqKQ" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    </div>

</div>

<div class="tbk-box">
    <table class="form-table" role="presentation">
        <?php $this->generate_settings_html(); ?>
    </table>
    <button name="save" class="button-primary woocommerce-save-button tbk-custom-save-button" type="submit" value="<?php _e('Guardar cambios', 'transbank_wc_plugin'); ?>"><?php _e('Guardar cambios', 'transbank_wc_plugin'); ?></button>
</div>

<?php
if ($environment === \Transbank\Webpay\Options::ENVIRONMENT_INTEGRATION) { ?>
    <div class="info-container" style="display: flex">


        <?php include_once 'components/info-validacion-webpay-oneclick-box.php'; ?>
        <?php include_once 'components/credenciales-box.php'; ?>

    </div>

<?php } ?>


<div id="my-content-id" style="display:none;overflow-y: scroll; max-height: 50vh;">
    <h2>¡Bienvenido a Webpay Oneclick!</h2>
    <img style="float: right; width: 180px; padding: 20px; display: block" src="<?php echo plugins_url('/images/oneclick.svg', dirname(__DIR__)); ?>" alt="">
    <div>
        <p>Este plugin también incluye integración con Webpay Oneclick Mall REST, para que tus clientes puedan
            inscribir su tarjeta de crédito, débito o prepago, y así puedan realizar sus siguientes compras con un solo
            click.
        </p>
        <p>Para Webpay Plus, necesitas un código de comercio y un Api Key. Para Webpay Oneclick necesitas otros códigos
            de comercio que te entregarán cuando contrates este producto. En este necesitas un código de comercio mall,
            un código de comercio tienda y un Api Key (llave secreta) para poder operar en producción.</p>
    </div>

    <p>Notarás que el plugin viene configurado en modo Integración, por lo que opera en un ambiente de pruebas, con
        dinero y tarjetas de prueba. </p>

    <h3>¿Que hago ahora?</h3>
    <p>Verifica que todo funcione correctamente. Realiza algunas compras con tarjetas de crédito y de débito, además de
        probar con transacciones aprobadas y rechazadas.</p>

    <h3>Documentación</h3>
    <p>Encuentra más detalles y funcionalidades en la <a target="_blank" href="http://transbankdevelopers.cl/plugin/woocommerce/" rel="noopener">documentación oficial del plugin</a></p>

    <h3>Conoce las novedades</h3>
    <iframe title="Video que muestra las novedades del plugin" width="100%" height="315" src="https://www.youtube.com/embed/aVElZf5xqKQ" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

</div>

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
<script>
    (function() {
        var qs, js, q, s, d = document,
            gi = d.getElementById,
            ce = d.createElement,
            gt = d.getElementsByTagName,
            id = "typef_orm_share",
            b = "https://embed.typeform.com/";
        if (!gi.call(d, id)) {
            js = ce.call(d, "script");
            js.id = id;
            js.src = b + "embed.js";
            q = gt.call(d, "script")[0];
            q.parentNode.insertBefore(js, q)
        }
    })()
</script>
