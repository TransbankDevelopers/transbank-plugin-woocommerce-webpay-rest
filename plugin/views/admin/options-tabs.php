<?php

use Transbank\WooCommerce\WebpayRest\Helpers\HealthCheckFactory;

if (!defined('ABSPATH')) {
    exit;
}

function tbk_is_nav_active($tab, $val)
{
    if ($tab === $val) {
        echo 'active';
    }
}
?>
<div class="wrap tbk-wrap">
    <h3><?php _e('Transbank', 'woocommerce'); ?></h3>
    <p><?php _e('Transbank es la empresa líder en negocios de medio de pago seguros en Chile.'); ?></p>

    <div class="tbk-container">
        <div class="tbk-nav-container">
            <div class="tbk-nav">
                <div class="nav-title">Configuración</div>
                <ul>
                    <li class="<?php tbk_is_nav_active($tab, 'options'); ?>"><a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_webpay_plus_rest&tbk_tab=options') ?>">
                            Webpay Plus <i class="icon fa fa-arrow-right"></i>
                        </a></li>
                    <li class="<?php tbk_is_nav_active($tab, 'options_oneclick'); ?>"><a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_oneclick_mall_rest&tbk_tab=options_oneclick') ?>">
                            Webpay Oneclick <i class="icon fa fa-arrow-right"></i>
                        </a></li>
                    <li class="<?php tbk_is_nav_active($tab, 'transactions'); ?>"><a href="<?php echo admin_url('admin.php?page=transbank_webpay_plus_rest&tbk_tab=transactions') ?>">
                            Transacciones <i class="icon fa fa-arrow-right"></i>
                        </a></li>
                    <li class="<?php tbk_is_nav_active($tab, 'healthcheck'); ?>"><a href="<?php echo admin_url('admin.php?page=transbank_webpay_plus_rest&tbk_tab=healthcheck') ?>">
                            Diagnóstico <i class="icon fa fa-arrow-right"></i>
                        </a></li>
                    <li class="<?php tbk_is_nav_active($tab, 'logs'); ?>"><a href="<?php echo admin_url('admin.php?page=transbank_webpay_plus_rest&tbk_tab=logs') ?>">
                            Registros (logs) <i class="icon fa fa-arrow-right"></i>
                        </a></li>
                    <li class="<?php tbk_is_nav_active($tab, 'phpinfo'); ?>"><a href="<?php echo admin_url('admin.php?page=transbank_webpay_plus_rest&tbk_tab=phpinfo') ?>">
                            PHP Info <i class="icon fa fa-arrow-right"></i>
                        </a></li>
                </ul>
            </div>
        </div>
        <div class="tbk-content">
            <?php
            if ($tab === 'options') {
                include __DIR__.'/admin-options.php';
            } elseif ($tab === 'options_oneclick') {
                include __DIR__.'/oneclick-admin-options.php';
            } elseif ($tab === 'logs') {
                include __DIR__.'/logs.php';
            } elseif ($tab === 'phpinfo') {
                include __DIR__.'/phpinfo.php';
            } elseif ($tab === 'transactions') {
                include __DIR__.'/transactions.php';
            } else {
                $healthcheck = HealthCheckFactory::create();
                $datos_hc = json_decode($healthcheck->printFullResume());
                include __DIR__.'/healthcheck.php';
            }
            ?>
        </div>
    </div>
<!--    -->
<!--    <h2 class="nav-tab-wrapper">-->
<!--        <a href="--><?php //echo admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_webpay_plus_rest&tbk_tab=options')?><!--"-->
<!--           class="nav-tab --><?php //if ($tab === 'options') {
//     echo 'nav-tab-active';
// }?><!--">Webpay Plus</a>-->
<!--        <a href="--><?php //echo admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_oneclick_mall_rest&tbk_tab=options_oneclick')?><!--"-->
<!--           class="nav-tab --><?php //if ($tab === 'options_oneclick') {
//     echo 'nav-tab-active';
// }?><!--">Webpay Oneclick</a>-->
<!--        <a href="--><?php //echo admin_url('admin.php?page=transbank_webpay_plus_rest&tbk_tab=healthcheck')?><!--"-->
<!--           class="nav-tab --><?php //if ($tab === 'healthcheck') {
//     echo 'nav-tab-active';
// }?><!--">Diagnóstico</a>-->
<!--        <a href="--><?php //echo admin_url('admin.php?page=transbank_webpay_plus_rest&tbk_tab=logs')?><!--"-->
<!--           class="nav-tab --><?php //if ($tab === 'logs') {
//     echo 'nav-tab-active';
// }?><!--">Registros (logs)</a>-->
<!--        <a href="--><?php //echo admin_url('admin.php?page=transbank_webpay_plus_rest&tbk_tab=phpinfo')?><!--"-->
<!--           class="nav-tab --><?php //if ($tab === 'phpinfo') {
//     echo 'nav-tab-active';
// }?><!--">PHP Info</a>-->
<!--    </h2>-->



</div>
