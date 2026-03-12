<?php

use Transbank\WooCommerce\WebpayRest\Admin\Notices\DismissNoticeAjax;
use Transbank\WooCommerce\WebpayRest\Admin\Notices\NoticeInscriptionDelete;
use Transbank\WooCommerce\WebpayRest\Controllers\TransactionStatusController;
use Transbank\WooCommerce\WebpayRest\Helpers\DatabaseTableInstaller;
use Transbank\WooCommerce\WebpayRest\Helpers\SessionMessageHelper;
use Transbank\WooCommerce\WebpayRest\Helpers\HposHelper;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\PaymentGateways\WC_Gateway_Transbank_Oneclick_Mall_REST;
use Transbank\WooCommerce\WebpayRest\PaymentGateways\WC_Gateway_Transbank_Webpay_Plus_REST;
use Transbank\WooCommerce\WebpayRest\Blocks\WCGatewayTransbankWebpayBlocks;
use Transbank\WooCommerce\WebpayRest\Blocks\WCGatewayTransbankOneclickBlocks;
use Transbank\WooCommerce\WebpayRest\Setup\ConfigMigrator;
use Transbank\WooCommerce\WebpayRest\Utils\ConnectionCheck;
use Transbank\WooCommerce\WebpayRest\Utils\TableCheck;
use Transbank\Plugin\Helpers\PluginLogger;
use Transbank\WooCommerce\WebpayRest\Utils\Template;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Transbank\WooCommerce\WebpayRest\Admin\Notices\AdminNoticeManager;
use Transbank\WooCommerce\WebpayRest\Admin\Notices\MissingWooCommerceNotice;
use Transbank\WooCommerce\WebpayRest\Admin\Notices\NoticeRenderer;
use Transbank\WooCommerce\WebpayRest\Admin\Notices\ReviewNotice;
use Transbank\WooCommerce\WebpayRest\Config\TransbankPluginSettings;
use Transbank\WooCommerce\WebpayRest\Setup\GatewaySettingsInstaller;

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
 * Plugin Name: Transbank Webpay
 * Plugin URI: https://www.transbankdevelopers.cl/plugin/woocommerce/webpay
 * Description: Recibe pagos en línea con Tarjetas de Crédito y Redcompra en tu WooCommerce a través de Webpay Plus y Webpay Oneclick.
 * Version: VERSION_REPLACE_HERE
 * Requires Plugins: woocommerce
 * Author: TransbankDevelopers
 * Author URI: https://www.transbank.cl
 * WC requires at least: 7.0
 * WC tested up to: 10.5.2
 */

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
$hposHelper = new HposHelper();
$hposExists = $hposHelper->checkIfHposExists();
add_action('plugins_loaded', 'registerPaymentGateways', 0);
add_action('wp_loaded', 'woocommerceTransbankInit');
register_activation_hook(__FILE__, 'activateTransbankModule');
add_action('add_meta_boxes', function () use ($hposExists) {
    addTransbankStatusMetaBox($hposExists);
});

add_action('init', function () {
    add_action('wp_ajax_check_connection', ConnectionCheck::class . '::check');
    add_action('wp_ajax_check_can_download_file', PluginLogger::class . '::checkCanDownloadLogFile');
    add_action('wp_ajax_download_log_file', PluginLogger::class . '::downloadLogFile');
    add_action('wp_ajax_get_transaction_status', [new TransactionStatusController(), 'getStatus']);
});

add_action('woocommerce_before_cart', 'transbank_rest_before_cart');

add_action('woocommerce_before_checkout_form', 'transbank_rest_check_cancelled_checkout');
add_action('admin_enqueue_scripts', function () {
    $slug = tbkAdminResolvePageSlug();

    if (!$slug) {
        return;
    }

    wp_enqueue_style('tbk-font-awesome', plugins_url('/css/font-awesome/all.css', __FILE__));

    $handle  = "tbk-admin-{$slug}";
    tbkAdminEnqueueStyleBundle("$handle-style", "css/admin-{$slug}-style.css");
    tbkAdminEnqueueScriptBundle($handle, "js/admin-{$slug}.js");
    tbkAdminEnqueueScriptBundle('tbk-admin-dismiss-notice', "js/admin-dismiss-notice.js");
    wp_localize_script($handle, 'ajax_object', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('my-ajax-nonce'),
    ]);

    tbkEnqueueAdminExtrasByScreen($slug, [
        'scripts' => [
            [
                'id' => 'admin-swal',
                'src' => 'js/swal.min.js',
                'screens' => [
                    'buy-order-webpay',
                    'buy-order-one-click',
                    'inscriptions',
                ],
            ],
        ],
    ]);
});

add_action('woocommerce_blocks_loaded', function () {
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once 'src/Blocks/WC_Gateway_Transbank_Webpay_Blocks.php';
        require_once 'src/Blocks/WC_Gateway_Transbank_Oneclick_Blocks.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new WCGatewayTransbankWebpayBlocks());
                $payment_method_registry->register(new WCGatewayTransbankOneclickBlocks());
            }
        );
    }
});

add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

if ($hposExists) {
    add_action('before_woocommerce_init', function () {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__,
                true
            );
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

function woocommerceTransbankInit()
{
    registerAdminMenu();
    registerPluginActionLinks();

    $setting = new TransbankPluginSettings();
    $renderer = new NoticeRenderer(__FILE__);

    (new DismissNoticeAjax($setting))->register();
    (new AdminNoticeManager(
        new MissingWooCommerceNotice($renderer),
        new ReviewNotice($renderer, $setting),
        new NoticeInscriptionDelete($renderer)
    ))->register();
}

function registerPaymentGateways()
{
    add_filter('woocommerce_payment_gateways', function ($methods) {
        $methods[] = WC_Gateway_Transbank_Webpay_Plus_REST::class;
        $methods[] = WC_Gateway_Transbank_Oneclick_Mall_REST::class;
        return $methods;
    });

    ConfigMigrator::maybeMigrate();
}

function registerAdminMenu()
{
    add_action('admin_menu', function () {
        add_submenu_page('woocommerce', __('Configuración de Webpay Plus', 'transbank_wc_plugin'), 'Webpay Plus', 'administrator', 'transbank_webpay_plus_rest', function () {
            $tab = filter_input(INPUT_GET, 'tbk_tab', FILTER_DEFAULT) ?? '';
            $tab = htmlspecialchars($tab, ENT_QUOTES, 'UTF-8');
            if (!in_array($tab, ['healthcheck', 'logs', 'transactions', 'inscriptions'])) {
                wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_webpay_plus_rest&tbk_tab=options'));
            }

            include_once __DIR__ . '/views/admin/options-tabs.php';
        }, null);

        add_submenu_page('woocommerce', __('Configuración de Webpay Plus', 'transbank_wc_plugin'), 'Webpay Oneclick', 'administrator', 'transbank_webpay_oneclick_rest', function () {
            wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_oneclick_mall_rest&tbk_tab=options'));
        }, null);
    });
}

function registerPluginActionLinks()
{
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($actionLinks) {
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

function addTransbankStatusMetaBox(bool $hPosExists)
{
    if ($hPosExists) {
        $screen = wc_get_container()
            ->get(CustomOrdersTableController::class)
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

                $orderId = $order->get_id();
                renderTransactionStatusMetaBox($orderId);
            },
            $screen,
            'side',
            'core'
        );
    } else {
        add_meta_box('transbank_check_payment_status', __('Verificar estado del pago', 'transbank_wc_plugin'), function ($post) {
            $order = new WC_Order($post->ID);
            $orderId = $order->get_id();
            renderTransactionStatusMetaBox($orderId);
        }, 'shop_order', 'side', 'core');
    }
}

function renderTransactionStatusMetaBox(int $orderId)
{
    $viewData = [];
    $transaction = TbkFactory::createTransactionService()->findFirstByOrderId($orderId);

    if ($transaction) {
        $viewData = [
            'viewData' => [
                'orderId' => $orderId,
                'token' => $transaction->token,
                'buyOrder' => $transaction->buy_order
            ]
        ];
    }

    (new Template())->render('admin/order/transaction-status.php', $viewData);
}

function activateTransbankModule()
{
    try {
        DatabaseTableInstaller::createTableIfNeeded();
        DatabaseTableInstaller::checkTables();
        GatewaySettingsInstaller::installDefaultsIfMissing();
        ConfigMigrator::maybeMigrate();
    } catch (Throwable $e) {
        $logger = TbkFactory::createLogger();
        $logger->logError('Error al activar el plugin de Transbank: ' . $e->getMessage());
        wp_die(
            'No se pudo activar el plugin de Transbank. Error: ' . esc_html($e->getMessage()),
            'Error de activación',
            ['back_link' => true]
        );
    }
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

/**
 * Returns the absolute filesystem path to the admin assets build directory.
 *
 * @return string Absolute path ending with a trailing slash.
 */
function tbkAdminAssetBaseDir(): string
{
    return plugin_dir_path(__FILE__) . 'assets/build/admin/';
}

/**
 * Returns the public URL to the admin assets build directory.
 *
 * @return string Full URL ending with a trailing slash.
 */
function tbkAdminAssetBaseUrl(): string
{
    return plugins_url('assets/build/admin/', __FILE__);
}

/**
 * Safely reads the modification time of a file.
 *
 * Wraps filemtime() to handle race conditions where a file may be deleted
 * between an is_readable() check and the actual filemtime() call (e.g. during
 * hot deploys). Returns null when the file is no longer accessible.
 *
 * @param  string      $filePath Absolute path to the file.
 * @return string|null Modification time as a string, or null on failure.
 */
function tbkSafeFilemtime(string $filePath): ?string
{
    if (!file_exists($filePath)) {
        return null;
    }

    $mtime = filemtime($filePath);

    return $mtime !== false ? (string) $mtime : null;
}

/**
 * Enqueues a JS bundle, loading its .asset.php metadata file when available.
 *
 * If the file is not readable this function returns silently without enqueuing
 * anything. A corrupted or invalid .asset.php will be ignored gracefully and
 * the fallback version (file mtime) will be used instead.
 *
 * @param  string $handle         Unique script handle.
 * @param  string $relativeJsPath Path relative to the admin assets build dir.
 * @return void
 */
function tbkAdminEnqueueScriptBundle(string $handle, string $relativeJsPath): void
{
    $baseDir = tbkAdminAssetBaseDir();
    $baseUrl = tbkAdminAssetBaseUrl();

    $relativeJsPath = ltrim($relativeJsPath, '/');
    $jsFile         = $baseDir . $relativeJsPath;

    if (!is_readable($jsFile)) {
        return;
    }

    $ver  = tbkSafeFilemtime($jsFile);
    $deps = [];

    $assetPhp = preg_replace('/\.js$/', '.asset.php', $jsFile);

    if (is_string($assetPhp) && is_readable($assetPhp)) {
        try {
            $asset = include_once $assetPhp;
        } catch (\Throwable) {
            $asset = [];
        }

        if (is_array($asset)) {
            $deps = isset($asset['dependencies']) && is_array($asset['dependencies'])
                ? $asset['dependencies']
                : [];

            if (
                isset($asset['version'])
                && is_string($asset['version'])
                && $asset['version'] !== ''
            ) {
                $ver = $asset['version'];
            }
        }
    }

    wp_enqueue_script(
        $handle,
        $baseUrl . $relativeJsPath,
        $deps,
        $ver,
        true
    );
}

/**
 * Enqueues a CSS bundle.
 *
 * If the file is not readable this function returns silently without enqueuing
 * anything.
 *
 * @param  string $handle          Unique style handle.
 * @param  string $relativeCssPath Path relative to the admin assets build dir.
 * @return void
 */
function tbkAdminEnqueueStyleBundle(string $handle, string $relativeCssPath): void
{
    $baseDir = tbkAdminAssetBaseDir();
    $baseUrl = tbkAdminAssetBaseUrl();

    $relativeCssPath = ltrim($relativeCssPath, '/');
    $cssFile         = $baseDir . $relativeCssPath;

    if (!is_readable($cssFile)) {
        return;
    }

    $ver = tbkSafeFilemtime($cssFile);

    wp_enqueue_style(
        $handle,
        $baseUrl . $relativeCssPath,
        [],
        $ver
    );
}

/**
 * Resolves the current admin page to a known internal slug.
 *
 * Checks the current WordPress screen ID first, then falls back to the
 * Transbank context derived from query string parameters.
 *
 * @return string|null The matched slug, or null when the screen is not mapped.
 */
function tbkAdminResolvePageSlug(): ?string
{
    $screenMap = [
        'shop_order'                 => 'transaction-status',
        'woocommerce_page_wc-orders' => 'transaction-status',
    ];

    $contextMap = [
        'webpay'       => 'buy-order-webpay',
        'oneclick'     => 'buy-order-one-click',
        'inscriptions' => 'inscriptions',
        'healthcheck'  => 'connection-check',
        'logs'         => 'logs',
        'transactions' => 'transactions',
    ];

    $screen   = function_exists('get_current_screen') ? get_current_screen() : null;
    $screenId = isset($screen->id) ? (string) $screen->id : '';

    if ($screenId !== '' && isset($screenMap[$screenId])) {
        return $screenMap[$screenId];
    }

    $context = function_exists('tbkGetContext') ? tbkGetContext() : 'none';

    if ($context !== '' && isset($contextMap[$context])) {
        return $contextMap[$context];
    }

    return null;
}

/**
 * Determines the current Transbank admin context from query string parameters.
 *
 * @return string One of: 'webpay', 'oneclick', 'inscriptions', 'healthcheck',
 *                'logs', 'transactions', 'options', or 'none'.
 */
function tbkGetContext(): string
{
    $page    = (string) (filter_input(INPUT_GET, 'page',    FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $section = (string) (filter_input(INPUT_GET, 'section', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $tbkTab  = (string) (filter_input(INPUT_GET, 'tbk_tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');

    $contextMap = [
        'wc-settings:transbank_webpay_plus_rest'    => 'webpay',
        'wc-settings:transbank_oneclick_mall_rest'  => 'oneclick',
    ];

    $key = "{$page}:{$section}";

    if (isset($contextMap[$key])) {
        return $contextMap[$key];
    }

    $context = $tbkTab !== '' ? $tbkTab : 'options';

    return $page === 'transbank_webpay_plus_rest' ? $context : 'none';
}

/**
 * Enqueues extra scripts and styles for a given admin page slug.
 *
 * Scripts and styles are only enqueued when the current slug matches one of
 * the values listed in each entry's 'screens' field. Already-enqueued handles
 * are skipped to prevent duplicates.
 *
 * @param  string|null $slug   Current resolved page slug (from tbkAdminResolvePageSlug()).
 *                             Passing null or an empty string is a no-op.
 * @param  array{
 *             scripts?: array<int, array{id: string, src: string, screens: string|string[], deps?: string[]}>,
 *             styles?:  array<int, array{id: string, src: string, screens: string|string[], deps?: string[]}>
 *         } $extras            Map of scripts and styles to conditionally enqueue.
 * @return void
 */
function tbkEnqueueAdminExtrasByScreen(?string $slug, array $extras): void
{
    if ($slug === null || $slug === '' || empty($extras)) {
        return;
    }

    $baseDir = plugin_dir_path(__FILE__);
    $baseUrl = plugin_dir_url(__FILE__);

    foreach (($extras['scripts'] ?? []) as $script) {
        tbkEnqueueExtraScript($slug, $script, $baseDir, $baseUrl);
    }

    foreach (($extras['styles'] ?? []) as $style) {
        tbkEnqueueExtraStyle($slug, $style, $baseDir, $baseUrl);
    }
}

function tbkEnqueueExtraScript(?string $slug, array $script, string $baseDir, string $baseUrl): void
{
    if (empty($script['id']) || empty($script['src']) || empty($script['screens'])) {
        return;
    }

    if (!in_array($slug, (array) $script['screens'], true)) {
        return;
    }

    $handle   = 'tbk-' . sanitize_key((string) $script['id']);
    $relative = ltrim((string) $script['src'], '/');
    $file     = $baseDir . $relative;

    if (!is_readable($file) || wp_script_is($handle, 'enqueued')) {
        return;
    }

    $deps = isset($script['deps']) && is_array($script['deps']) ? $script['deps'] : [];

    $inFooter = isset($script['in_footer']) && $script['in_footer'] === true;
    wp_enqueue_script($handle, $baseUrl . $relative, $deps, tbkSafeFilemtime($file), $inFooter);
}

function tbkEnqueueExtraStyle(?string $slug, array $style, string $baseDir, string $baseUrl): void
{
    if (empty($style['id']) || empty($style['src']) || empty($style['screens'])) {
        return;
    }

    if (!in_array($slug, (array) $style['screens'], true)) {
        return;
    }

    $handle   = 'tbk-' . sanitize_key((string) $style['id']);
    $relative = ltrim((string) $style['src'], '/');
    $file     = $baseDir . $relative;

    if (!is_readable($file) || wp_style_is($handle, 'enqueued')) {
        return;
    }

    $deps = isset($style['deps']) && is_array($style['deps']) ? $style['deps'] : [];

    wp_enqueue_style($handle, $baseUrl . $relative, $deps, tbkSafeFilemtime($file));
}
