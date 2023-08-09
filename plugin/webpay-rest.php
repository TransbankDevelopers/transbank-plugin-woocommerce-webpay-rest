<?php
use Transbank\WooCommerce\WebpayRest\Controllers\TransactionStatusController;
use Transbank\WooCommerce\WebpayRest\Helpers\DatabaseTableInstaller;
use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;
use Transbank\WooCommerce\WebpayRest\Helpers\SessionMessageHelper;
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
require_once plugin_dir_path(__FILE__).'libwebpay/ReportGenerator.php';

register_activation_hook(__FILE__, 'transbank_webpay_rest_on_webpay_rest_plugin_activation');
add_action('admin_init', 'on_transbank_rest_webpay_plugins_loaded');
add_action('wp_ajax_check_connection', 'ConnectionCheck::check');
add_action('wp_ajax_check_exist_tables', 'TableCheck::check');
add_action('wp_ajax_get_transaction_status', TransactionStatusController::class.'::status');
add_action('wp_ajax_show_php_info_report', \Transbank\Woocommerce\ReportGenerator::class.'::showPhpInfoReport');
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
     * @property string icon
     * @property string method_title
     * @property string title
     * @property string description
     * @property string id
     */
    class WC_Gateway_Transbank_Webpay_Plus_REST extends WC_Payment_Gateway
    {
        use TransbankRESTPaymentGateway;

        const WOOCOMMERCE_API_SLUG = 'wc_gateway_transbank_webpay_plus_rest';
        private static $URL_FINAL;

        protected $plugin_url;
        protected $log;
        protected $config;

        public function __construct()
        {
            self::$URL_FINAL = home_url('/').'?wc-api=TransbankWebpayRestThankYouPage';

            $this->id = 'transbank_webpay_plus_rest';
            $this->icon = plugin_dir_url(__FILE__).'images/webpay.png';
            $this->method_title = __('Transbank Webpay Plus', 'transbank_webpay_plus_rest');
            $this->title = 'Transbank Webpay Plus';
            $this->description = 'Permite el pago de productos y/o servicios, con tarjetas de crédito, débito y prepago a través de Webpay Plus';
            $this->method_description = $description;
            $this->plugin_url = plugins_url('/', __FILE__);
            $this->log = new LogHandler();
            $this->interactsWithFullLog = new InteractsWithFullLog();

            $this->supports = [
                'products',
                'refunds',
            ];

            $this->config = [
                'MODO'                 => trim($this->get_option('webpay_rest_environment', 'TEST')),
                'COMMERCE_CODE'        => trim($this->get_option('webpay_rest_commerce_code', WebpayPlus::DEFAULT_COMMERCE_CODE)),
                'API_KEY'              => $this->get_option('webpay_rest_api_key', WebpayPlus::DEFAULT_API_KEY),
                'ECOMMERCE'            => 'woocommerce',
                'STATUS_AFTER_PAYMENT' => $this->get_option('webpay_rest_after_payment_order_status', ''),
            ];

            /**
             * Carga configuración y variables de inicio.
             **/
            $this->init_form_fields();
            $this->init_settings();

            add_action('woocommerce_thankyou', [new ThankYouPageController($this->config), 'show'], 1);
            add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'process_admin_options']);
            add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'registerPluginVersion']);
            add_action('woocommerce_api_wc_gateway_'.$this->id, [$this, 'check_ipn_response']);

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }

        public function process_refund($order_id, $amount = null, $reason = '')
        {
            $order = new WC_Order($order_id);
            $transaction = Transaction::getApprovedByOrderId($order_id);

            if (!$transaction) {
                $order->add_order_note('Se intentó anular transacción, pero no se encontró en la base de datos de transacciones de webpay plus. ');
                do_action('transbank_webpay_plus_refund_transaction_not_found', $order, $transaction);

                return false;
            }
            $response = [];

            try {
                $sdk = new TransbankSdkWebpayRest();
                $response = $sdk->refund($transaction->token, round($amount));
                $jsonResponse = json_encode($response, JSON_PRETTY_PRINT);
            } catch (Exception $e) {
                $order->add_order_note('<strong>Error al anular:</strong><br />'.$e->getMessage());
                do_action('transbank_webpay_plus_refund_failed', $order, $transaction, $e->getMessage());

                throw new Exception('Error al anular: '.$e->getMessage());
            }

            if ($response->getType() === 'REVERSED' || ($response->getType() === 'NULLIFIED' && (int) $response->getResponseCode() === 0)) {
                $this->addRefundOrderNote($response, $order, $amount, $jsonResponse);
                do_action('transbank_webpay_plus_refund_completed', $order, $transaction, $response);

                return true;
            } else {
                $order->add_order_note('Anulación a través de Webpay FALLIDA. '.
                    "\n\n".$jsonResponse);
                do_action('transbank_webpay_plus_refund_failed', $order, $transaction);

                throw new Exception('Anulación a través de Webpay fallida.');
            }

            return false;
        }

        public function registerPluginVersion()
        {

            $commerceCode = $this->get_option('webpay_rest_commerce_code');

            $pluginVersion = $this->getPluginVersion();

            (new PluginVersion())->registerVersion(
                $commerceCode,
                $pluginVersion,
                wc()->version,
                PluginVersion::ECOMMERCE_WOOCOMMERCE,
                $this->get_option('webpay_rest_environment'),
                'webpay'
            );
        }

        /**
         * Comprueba configuración de moneda (Peso Chileno).
         **/
        public static function is_valid_for_use()
        {
            return in_array(get_woocommerce_currency(), ['CLP']);
        }

        /**
         * Inicializar campos de formulario.
         **/
        public function init_form_fields()
        {
			$this->description = $this->get_option('webpay_rest_description');
            $this->form_fields = [
                'enabled' => [
                    'title'   => __('Activar/Desactivar', 'transbank_webpay_plus_rest'),
                    'type'    => 'checkbox',
                    'default' => 'yes',
                ],
                'webpay_rest_environment' => [
                    'title'       => __('Ambiente', 'transbank_webpay_plus_rest'),
                    'type'        => 'select',
                    'desc_tip'    => 'Define si el plugin operará en el ambiente de pruebas (integración) o en el
                    ambiente real (producción). <br /><br />Si defines el ambiente como "Integración" <strong>no</strong> se usarán el código de
                    comercio y llave secreta que tengas configurado abajo, ya que se usará el código de comercio especial del ambiente de pruebas.',
                    'options' => [
                        'TEST' => __('Integración', 'transbank_webpay_plus_rest'),
                        'LIVE' => __('Producción', 'transbank_webpay_plus_rest'),
                    ],
                    'default' => 'TEST',
                ],
                'webpay_rest_commerce_code' => [
                    'title'       => __('Código de Comercio Producción', 'transbank_webpay_plus_rest'),
                    'placeholder' => 'Ej: 597012345678',
                    'desc_tip'    => 'Indica tu código de comercio para el ambiente de producción. <br /><br />Este se te entregará al completar el proceso de afiliación comercial. <br /><br />Siempre comienza con 5970 y debe tener 12 dígitos. Si el tuyo tiene 8, antepone 5970. ',
                    'type'        => 'text',
                    'default'     => '',
                ],
                'webpay_rest_api_key' => [
                    'title'       => __('API Key (llave secreta) producción', 'transbank_webpay_plus_rest'),
                    'type'        => 'text',
                    'placeholder' => 'Ej: XXXXXXXXXXXXXXXXXXXXXXXXXXXX',
                    'desc_tip'    => 'Esta llave privada te la entregará Transbank luego de que completes el proceso de validación (link más abajo). <br /><br />No la compartas con nadie una vez que la tengas. ',
                    'default'     => '',
                ],
                'webpay_rest_after_payment_order_status' => [
                    'title'       => __('Order Status', 'transbank_webpay_plus_rest'),
                    'type'        => 'select',
                    'desc_tip'    => 'Define el estado de la orden luego del pago exitoso.',
                    'options' => [
                        '' => 'Default',
                        'processing' => 'Processing',
                        'completed'  => 'Completed',
                    ],
                    'default' => '',
                ],
				'webpay_rest_description' => [
                    'title'       => __('Descripción', 'transbank_webpay_plus_rest'),
                    'type'        => 'textarea',
                    'default'     => $description,
                    'placeholder' => $description,
                ],
            ];
        }

        /**
         * Obtiene respuesta IPN (Instant Payment Notification).
         **/
        public function check_ipn_response()
        {
            @ob_clean();
            if (isset($_POST)) {
                header('HTTP/1.1 200 OK');
                $data = ($_SERVER['REQUEST_METHOD'] === 'GET') ? $_GET : $_POST;

                return (new ResponseController($this->config))->response($data);
            } else {
                echo 'Ocurrió un error al procesar su compra';
            }
        }

        /**
         * Procesar pago y retornar resultado.
         **/
        public function process_payment($order_id)
        {
            global $wpdb;
            $order = new WC_Order($order_id);
            $amount = (int) number_format($order->get_total(), 0, ',', '');
            $sessionId = uniqid();
            $buyOrder = $order_id;
            $returnUrl = add_query_arg('wc-api', static::WOOCOMMERCE_API_SLUG, home_url('/'));

            $transbankSdkWebpay = new TransbankSdkWebpayRest($this->config);
            do_action('transbank_webpay_plus_starting_transaction', $order);

            try {
                $result = $transbankSdkWebpay->createTransaction($amount, $sessionId, $buyOrder, $returnUrl);
            } catch (\Throwable $e) {
                $errorMessage = ErrorHelper::getErrorMessageBasedOnTransbankSdkException($e);

                return wc_add_notice($errorMessage, 'error');
            }

            if (!isset($result['token_ws'])) {
                wc_add_notice(
                    'Ocurrió un error al intentar conectar con WebPay Plus. Por favor intenta mas tarde.<br/>',
                    'error'
                );
              
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

        return;
    }
    $pluginObject = new WC_Gateway_Transbank_Webpay_Plus_REST();
    $pluginObject->registerPluginVersion();
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

add_action('add_meta_boxes', function () {
    add_meta_box('transbank_check_payment_status', __('Verificar estado del pago', 'transbank_wc_plugin'), function ($post) {
        $order = new WC_Order($post->ID);
        $transaction = Transaction::getApprovedByOrderId($order->get_id());
        include_once __DIR__.'/views/get-status.php';
    }, 'shop_order', 'side', 'core');
});

add_action('admin_menu', function () {
    //create new top-level menu
    add_submenu_page('woocommerce', __('Configuración de Webpay Plus', 'transbank_wc_plugin'), 'Webpay Plus', 'administrator', 'transbank_webpay_plus_rest', function () {
        $tab = filter_input(INPUT_GET, 'tbk_tab', FILTER_SANITIZE_STRING);
        if (!in_array($tab, ['healthcheck', 'logs', 'phpinfo', 'transactions'])) {
            wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_webpay_plus_rest&tbk_tab=options'));
        }

        $log = new LogHandler();
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
