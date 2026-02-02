<?php
if (!defined('ABSPATH')) {
    return;
}
$table = new \Transbank\WooCommerce\WebpayRest\Admin\ListTable\OneclickInscriptionsTable();
$table->prepare_items();
$defaultPerPage = 15;
$perPage = isset($_GET['per_page']) ? absint($_GET['per_page']) : $defaultPerPage;
$perPageOptions = [10, 15, 20, 50, 100];
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = $defaultPerPage;
}
?>
<div class="tbk-box">
    <h3>Lista de inscripciones Oneclick</h3>
    <p>En esta lista encontrarás el listado de inscripciones de Oneclick que Transbank ha procesado.</p>
    <form method="get" class="tbk-inscriptions-per-page">
        <input type="hidden" name="page" value="transbank_webpay_plus_rest">
        <input type="hidden" name="tbk_tab" value="inscriptions">
        <label for="tbk-inscriptions-per-page">Inscripciones por página</label>
        <select name="per_page" id="tbk-inscriptions-per-page">
            <?php foreach ($perPageOptions as $option) : ?>
                <option value="<?php echo esc_attr($option); ?>" <?php selected($perPage, $option); ?>>
                    <?php echo esc_html((string) $option); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="button tbk-button-danger">Aplicar</button>
    </form>
    <?php $table->display(); ?>
</div>
