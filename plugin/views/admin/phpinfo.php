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
               href="<?php echo admin_url('admin-ajax.php'); ?>?action=show_php_info_report"
               target="_blank">
                Mostrar PHP Info en Html
            </a>
            <br>
        </fieldset>
    </div>
</div>
