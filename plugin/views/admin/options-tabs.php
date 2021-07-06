<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap"><h3><?php _e('Transbank', 'woocommerce'); ?></h3>
    <p><?php _e('Transbank es la empresa líder en negocios de medio de pago seguros en Chile.'); ?></p>


    <h2 class="nav-tab-wrapper">
        <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_webpay_plus_rest&tbk_tab=options') ?>"
           class="nav-tab <?php if ($tab === 'options') {
    echo 'nav-tab-active';
} ?>">Webpay Plus</a>
        <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_oneclick_mall_rest&tbk_tab=options_oneclick') ?>"
           class="nav-tab <?php if ($tab === 'options_oneclick') {
    echo 'nav-tab-active';
} ?>">Webpay Oneclick</a>
        <a href="<?php echo admin_url('admin.php?page=transbank_webpay_plus_rest&tbk_tab=healthcheck') ?>"
           class="nav-tab <?php if ($tab === 'healthcheck') {
    echo 'nav-tab-active';
} ?>">Diagnóstico</a>
        <a href="<?php echo admin_url('admin.php?page=transbank_webpay_plus_rest&tbk_tab=logs') ?>"
           class="nav-tab <?php if ($tab === 'logs') {
    echo 'nav-tab-active';
} ?>">Registros (logs)</a>
        <a href="<?php echo admin_url('admin.php?page=transbank_webpay_plus_rest&tbk_tab=phpinfo') ?>"
           class="nav-tab <?php if ($tab === 'phpinfo') {
    echo 'nav-tab-active';
} ?>">PHP Info</a>
    </h2>


    <div class="content">
        <?php
        if ($tab === 'options') {
            include __DIR__.'/admin-options.php';
        } elseif ($tab === 'options_oneclick') {
            include __DIR__.'/oneclick-admin-options.php';
        } elseif ($tab === 'logs') {
            include __DIR__.'/logs.php';
        } elseif ($tab === 'phpinfo') {
            include __DIR__.'/phpinfo.php';
        } else {
            $datos_hc = json_decode($healthcheck->printFullResume());
            include __DIR__.'/healthcheck.php';
        }
        ?>
    </div>
</div>
