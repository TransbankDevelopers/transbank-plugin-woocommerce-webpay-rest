<div class="tbk-box">
    <h3>Lista de transacciones Transbank</h3>
    <p>En esta lista encontrarás el listado de transacciones que Transbank ha procesado. <br>
        Se incluyen transacciones desde el momento en que se inicia el intento de pago tanto en Webpay Plus como Webpay Oneclick.</p>
    <?php
    $table = new \Transbank\WooCommerce\WebpayRest\Helpers\WebpayTransactionsTable();
    $table->prepare_items();
    $table->display();
    ?>
</div>
