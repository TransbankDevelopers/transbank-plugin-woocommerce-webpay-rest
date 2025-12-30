<?php
if (!defined('ABSPATH')) {
    return;
}
$table = new \Transbank\WooCommerce\WebpayRest\Admin\ListTable\OneclickInscriptionsTable();
$table->prepare_items();
?>
<div class="tbk-box">
    <h3>Lista de inscripciones Oneclick</h3>
    <p>En esta lista encontrarás el listado de inscripciones de Oneclick que Transbank ha procesado.</p>
    <?php $table->display(); ?>
</div>
