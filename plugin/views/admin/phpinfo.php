<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="tbk-box">
    <div id="php_info" class="tab-pane">
        <fieldset class="tbk_info">
            <legend class="tbk_title_legend">Informe PHP info</legend>
            <a class="button-primary" href="<?php echo admin_url('admin-ajax.php'); ?>?action=show_php_info_report" target="_blank" rel="noopener">
                Mostrar PHP Info en Html
            </a>
            <br>
        </fieldset>
    </div>
</div>
