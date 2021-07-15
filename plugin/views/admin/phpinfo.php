<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="tbk-box">
    <div id="php_info" class="tab-pane">
        <fieldset class="tbk_info">
            <h3 class="tbk_title_h3">Informe PHP info</h3>
            <a class="button-primary"
               href="<?php echo admin_url('admin-ajax.php'); ?>?action=download_report&document=php_info"
               target="_blank">
                Crear PDF de PHP info
            </a>
            <br>
        </fieldset>

        <fieldset>
            <h3 class="tbk_title_h3">PHP info</h3>
            <span
                style="font-size: 10px; font-family:monospace; display: block;overflow: hidden;">
                                            <?php echo $datos_hc->php_info->string->content; ?>
                                        </span><br>
        </fieldset>
    </div>
</div>
