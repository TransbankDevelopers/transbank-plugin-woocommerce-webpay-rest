<?php
use Transbank\WooCommerce\WebpayRest\Controllers\TransactionStatusController;
use Transbank\WooCommerce\WebpayRest\Helpers\DatabaseTableInstaller;
use Transbank\WooCommerce\WebpayRest\Helpers\SessionMessageHelper;
use Transbank\WooCommerce\WebpayRest\Helpers\HposHelper;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\PaymentGateways\WC_Gateway_Transbank_Oneclick_Mall_REST;
use Transbank\WooCommerce\WebpayRest\PaymentGateways\WC_Gateway_Transbank_Webpay_Plus_REST;
use Transbank\WooCommerce\WebpayRest\Blocks\WCGatewayTransbankWebpayBlocks;
use Transbank\WooCommerce\WebpayRest\Blocks\WCGatewayTransbankOneclickBlocks;
use Transbank\WooCommerce\WebpayRest\Utils\ConnectionCheck;
use Transbank\WooCommerce\WebpayRest\Utils\TableCheck;

if (!defined('ABSPATH')) {
    return;
}
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
 * WC requires at least: 7.0
 * WC tested up to: 8.3
 */

require_once plugin_dir_path(__FILE__).'vendor/autoload.php';

add_action('plugins_loaded', 'registerPaymentGateways', 0);
add_action('wp_loaded', 'woocommerceTransbankInit');
add_action('admin_init', 'on_transbank_rest_webpay_plugins_loaded');

add_action('wp_ajax_check_connection', ConnectionCheck::class.'::check');
add_action('wp_ajax_check_exist_tables', TableCheck::class.'::check');
add_action('wp_ajax_get_transaction_status', TransactionStatusController::class.'::getStatus');
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

add_action('woocommerce_blocks_loaded', function() {
    if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ){
        require_once 'src/Blocks/WC_Gateway_Transbank_Webpay_Blocks.php';
        require_once 'src/Blocks/WC_Gateway_Transbank_Oneclick_Blocks.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                $payment_method_registry->register( new WCGatewayTransbankWebpayBlocks() );
                $payment_method_registry->register( new WCGatewayTransbankOneclickBlocks() );
            }
        );
    }
});

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

$hposHelper = new HposHelper();
$hPosExists = $hposHelper->checkIfHposExists();
if ($hPosExists)
{
    add_action('before_woocommerce_init', function () {
        if (class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__,
                true);
        }
    });
}


//Start sessions if not already done
add_action('init', function () {
    if (!headers_sent() && '' == session_id()) {
        session_start([
            'read_and_close' => true,
        ]);
    }
});

function woocommerceTransbankInit() {
    if (!class_exists('WC_Payment_Gateway')) {
        noticeMissingWoocommerce();
        return;
    }

    registerAdminMenu();
    registerPluginActionLinks();
}

function registerPaymentGateways() {
    add_filter('woocommerce_payment_gateways', function($methods) {
        $methods[] = WC_Gateway_Transbank_Webpay_Plus_REST::class;
        $methods[] = WC_Gateway_Transbank_Oneclick_Mall_REST::class;
        return $methods;
    });
}

function registerAdminMenu() {
    add_action('admin_menu', function () {
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
}

function registerPluginActionLinks() {
    add_filter('plugin_action_links_'.plugin_basename(__FILE__), function ($actionLinks) {
        $webpaySettingsLink = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_webpay_plus_rest'),
            'Configurar Webpay Plus'
        );
        $oneclickSettingsLink = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_oneclick_mall_rest'),
            'Configurar Webpay Oneclick'
        );
        $newLinks = [
            $webpaySettingsLink,
            $oneclickSettingsLink,
        ];

        return array_merge($actionLinks, $newLinks);
    });
}

function on_transbank_rest_webpay_plugins_loaded()
{
    DatabaseTableInstaller::createTableIfNeeded();
}

function transbank_rest_remove_database()
{
    DatabaseTableInstaller::deleteTable();
}

register_uninstall_hook(__FILE__, 'transbank_rest_remove_database');

if ($hPosExists)
{
    add_action('add_meta_boxes', function () {
        $screen = wc_get_container()
            ->get(Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)
            ->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';
        add_meta_box(
            'transbank_check_payment_status',
            __('Verificar estado del pago', 'transbank_wc_plugin'),
            function ($post_or_order_object) {
                $order = ($post_or_order_object instanceof WP_Post)
                ? wc_get_order($post_or_order_object->ID)
                : $post_or_order_object;
                $transaction = Transaction::getApprovedByOrderId($order->get_id());
                include_once __DIR__.'/views/get-status.php';
            },
            $screen,
            'side',
            'core');
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

function noticeMissingWoocommerce() {
    add_action(
        'admin_notices',
        function () {
            $noticeDescription = "WooCommerce no se encuentra activo o no está instalado.";
            $actionButton = [];
            $isWooInstalled = false;
            $isWooActivated = false;
            $currentUserCanInstallPlugins = current_user_can('install_plugins');
            $currentUserCanActivatePlugins = current_user_can('activate_plugins');

            $tbkLogo = sprintf('%s%s', plugin_dir_url(__FILE__), './images/tbk-logo.png');

            $activateLink = wp_nonce_url(
                self_admin_url('plugins.php?action=activate&plugin=woocommerce/woocommerce.php&plugin_status=all'),
                'activate-plugin_woocommerce/woocommerce.php'
            );

            $installLink = wp_nonce_url(
                self_admin_url('update.php?action=install-plugin&plugin=woocommerce'),
                'install-plugin_woocommerce'
            );

            if (function_exists('get_plugins')) {
                $allPlugins  = get_plugins();
                $isWooInstalled = !empty($allPlugins['woocommerce/woocommerce.php']);
            }

            if(function_exists('is_plugin_active')) {
                $isWooActivated = is_plugin_active('woocommerce/woocommerce.php');
            }

            $actionButton['text'] = 'Revisar Woocommerce';
            $actionButton['action'] = 'https://wordpress.org/plugins/woocommerce/';

            if (!$isWooInstalled && $currentUserCanInstallPlugins) {
                $actionButton['text'] = 'Instalar Woocommerce';
                $actionButton['action'] = esc_html($installLink);
                $noticeDescription = "Woocommerce no se encuentra instalado.";
            }

            if ($isWooInstalled && !$isWooActivated && $currentUserCanActivatePlugins) {
                $actionButton['text'] = 'Activar Woocommerce';
                $actionButton['action'] = esc_html($activateLink);
                $noticeDescription = "Woocommerce no se encuentra activado.";
            }

            include_once(plugin_dir_path(__FILE__) .'views/admin/components/notice-missing-woocommerce.php');
        }
    );
}
