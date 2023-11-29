<?php
use Transbank\WooCommerce\WebpayRest\Controllers\TransactionStatusController;
use Transbank\WooCommerce\WebpayRest\Helpers\DatabaseTableInstaller;
use Transbank\WooCommerce\WebpayRest\Helpers\SessionMessageHelper;
use Transbank\WooCommerce\WebpayRest\Helpers\HposHelper;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\PaymentGateways\WC_Gateway_Transbank_Oneclick_Mall_REST;
use Transbank\WooCommerce\WebpayRest\PaymentGateways\WC_Gateway_Transbank_Webpay_Plus_REST;

if (!defined('ABSPATH')) {
    exit();
} // Exit if accessed directly

/**
 * The plugin bootstrap file.
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @wordpress-plugin
 * Plugin Name: Transbank Webpay REST
 * Plugin URI: https://www.transbankdevelopers.cl/plugin/woocommerce/webpay
 * Description: Recibe pagos en línea con Tarjetas de Crédito y Redcompra en tu WooCommerce a través de Webpay Plus y Webpay Oneclick.
 * Version: VERSION_REPLACE_HERE
 * Author: TransbankDevelopers
 * Author URI: https://www.transbank.cl
 * WC requires at least: 3.4.0
 * WC tested up to: 5.5.1
 */
add_action('plugins_loaded', 'woocommerce_transbank_rest_init', 0);

$transbankPluginData = null;
//todo: Eliminar todos estos require y usar PSR-4 de composer
require_once plugin_dir_path(__FILE__).'vendor/autoload.php';
require_once plugin_dir_path(__FILE__).'libwebpay/ConnectionCheck.php';
require_once plugin_dir_path(__FILE__).'libwebpay/TableCheck.php';

register_activation_hook(__FILE__, 'transbank_webpay_rest_on_webpay_rest_plugin_activation');
add_action('admin_init', 'on_transbank_rest_webpay_plugins_loaded');
add_action('wp_ajax_check_connection', 'ConnectionCheck::check');
add_action('wp_ajax_check_exist_tables', 'TableCheck::check');
add_action('wp_ajax_get_transaction_status', TransactionStatusController::class.'::status');
add_filter('woocommerce_payment_gateways', 'woocommerce_add_transbank_gateway');
add_action('woocommerce_before_cart', 'transbank_rest_before_cart');

add_action('woocommerce_subscription_failing_payment_method_updated_transbank_oneclick_mall_rest', [WC_Gateway_Transbank_Oneclick_Mall_REST::class, 'subscription_payment_method_updated'], 10, 3);

add_action('woocommerce_before_checkout_form', 'transbank_rest_check_cancelled_checkout');
add_action('admin_enqueue_scripts', function () {
    wp_enqueue_style('tbk-styles', plugins_url('/css/tbk.css', __FILE__), [], '1.1');
    wp_enqueue_style('tbk-font-awesome', plugins_url('/css/font-awesome/all.css', __FILE__));
    wp_enqueue_script('tbk-ajax-script', plugins_url('/js/admin.js', __FILE__), ['jquery']);
    wp_enqueue_script('tbk-thickbox', plugins_url('/js/swal.min.js', __FILE__));
    wp_localize_script('tbk-ajax-script', 'ajax_object', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('my-ajax-nonce'),
    ]);
});

add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'transbank_webpay_rest_add_rest_action_links');


$hposHelper = new HposHelper();
$hPosExists = $hposHelper->checkIfHposExists();
if ($hPosExists)
{
    add_action('before_woocommerce_init', function () {
        if (class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    });
}


//Start sessions if not already done
add_action('init', function () {
    global $transbankPluginData;

    try {
        $transbankPluginData = get_plugin_data(__FILE__);
    } catch (Throwable $e) {
    }

    if (!headers_sent() && '' == session_id()) {
        session_start([
            'read_and_close' => true,
        ]);
    }
});

function woocommerce_transbank_rest_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once __DIR__.'/src/Tokenization/WC_Payment_Token_Oneclick.php';



    /**
     * Añadir Transbank Plus a Woocommerce.
     **/
    function woocommerce_add_transbank_gateway($methods)
    {
        $methods[] = WC_Gateway_Transbank_Webpay_Plus_REST::class;
        $methods[] = WC_Gateway_Transbank_Oneclick_Mall_REST::class;

        return $methods;
    }

    /**
     * Muestra detalle de pago a Cliente a finalizar compra.
     **/
    function pay_transbank_webpay_content($orderId)
    {
    }
}

function transbank_webpay_rest_add_rest_action_links($links)
{
    $newLinks = [
        '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_webpay_plus_rest').'">Configurar Webpay Plus</a>',
        '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_oneclick_mall_rest').'">Configurar Webpay Oneclick</a>',
    ];

    return array_merge($links, $newLinks);
}

function transbank_webpay_rest_on_webpay_rest_plugin_activation()
{
    woocommerce_transbank_rest_init();
    if (!class_exists(WC_Gateway_Transbank_Webpay_Plus_REST::class)) {
        exit('Se necesita tener WooCommerce instalado y activo para poder activar este plugin');
    }
}

function on_transbank_rest_webpay_plugins_loaded()
{
    DatabaseTableInstaller::createTableIfNeeded();
}

function transbank_rest_remove_database()
{
    DatabaseTableInstaller::deleteTable();
}

add_action('admin_notices', function () {
    if (!class_exists(WC_Gateway_Transbank_Webpay_Plus_REST::class)) {
        return;
    }
    if (!WC_Gateway_Transbank_Webpay_Plus_REST::is_valid_for_use()) {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Woocommerce debe estar configurado en pesos chilenos (CLP) para habilitar Webpay', 'transbank_wc_plugin'); ?></p>
        </div>
        <?php
    }
});
register_uninstall_hook(__FILE__, 'transbank_rest_remove_database');

if ($hPosExists)
{
    add_action('add_meta_boxes', function () {
        $screen = wc_get_container()->get(Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';
        add_meta_box('transbank_check_payment_status', __('Verificar estado del pago', 'transbank_wc_plugin'), function ($post_or_order_object) {
            $order = ($post_or_order_object instanceof WP_Post) ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;
            $transaction = Transaction::getApprovedByOrderId($order->get_id());
            include_once __DIR__.'/views/get-status.php';
        }, $screen, 'side', 'core');
    });
}
else
{
    add_action('add_meta_boxes', function () {
        add_meta_box('transbank_check_payment_status', __('Verificar estado del pago', 'transbank_wc_plugin'), function ($post) {
            $order = new WC_Order($post->ID);
            $transaction = Transaction::getApprovedByOrderId($order->get_id());
            include_once __DIR__.'/views/get-status.php';
        }, 'shop_order', 'side', 'core');
    });
}

add_action('admin_menu', function () {
    //create new top-level menu
    add_submenu_page('woocommerce', __('Configuración de Webpay Plus', 'transbank_wc_plugin'), 'Webpay Plus', 'administrator', 'transbank_webpay_plus_rest', function () {
        $tab = filter_input(INPUT_GET, 'tbk_tab', FILTER_SANITIZE_STRING);
        if (!in_array($tab, ['healthcheck', 'logs', 'transactions'])) {
            wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_webpay_plus_rest&tbk_tab=options'));
        }
        include_once __DIR__.'/views/admin/options-tabs.php';
    }, null);

    add_submenu_page('woocommerce', __('Configuración de Webpay Plus', 'transbank_wc_plugin'), 'Webpay Oneclick', 'administrator', 'transbank_webpay_oneclick_rest', function () {
        wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_oneclick_mall_rest&tbk_tab=options'));
    }, null);
});

function transbank_rest_before_cart()
{
    SessionMessageHelper::printMessage();
}

function transbank_rest_check_cancelled_checkout()
{
    $cancelledOrder = $_GET['transbank_cancelled_order'] ?? false;
    $cancelledWebpayPlusOrder = $_GET['transbank_webpayplus_cancelled_order'] ?? false;
    if ($cancelledWebpayPlusOrder) {
        wc_print_notice(__('Cancelaste la transacción durante el formulario de Webpay Plus.', 'transbank_wc_plugin'), 'error');
    }
    if ($cancelledOrder) {
        wc_print_notice(__('Cancelaste la inscripción durante el formulario de Webpay.', 'transbank_wc_plugin'), 'error');
    }
}
