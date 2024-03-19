<?php

if (!defined('ABSPATH')) {
    return;
}

use Transbank\WooCommerce\WebpayRest\Controllers\LogController;
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
                </ul>
            </div>
        </div>
        <div class="tbk-content">
            <?php
            if ($tab === 'options') {
                include_once __DIR__.'/admin-options.php';
            } elseif ($tab === 'options_oneclick') {
                include_once __DIR__.'/oneclick-admin-options.php';
            } elseif ($tab === 'logs') {
                (new LogController)->show();
            } elseif ($tab === 'transactions') {
                include_once __DIR__.'/transactions.php';
            } else {
                include_once __DIR__.'/healthcheck.php';
            }
            ?>
        </div>
    </div>
</div>
