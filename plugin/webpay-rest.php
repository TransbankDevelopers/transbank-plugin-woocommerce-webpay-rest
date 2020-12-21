<?php
use Transbank\Webpay\Options;
use Transbank\WooCommerce\WebpayRest\Controllers\ResponseController;
use Transbank\WooCommerce\WebpayRest\Controllers\ThankYouPageController;
use Transbank\WooCommerce\WebpayRest\Controllers\TransactionStatusController;
use Transbank\WooCommerce\WebpayRest\Helpers\ConfigProvider;
use Transbank\WooCommerce\WebpayRest\Helpers\HealthCheck;
use Transbank\WooCommerce\WebpayRest\Helpers\HealthCheckFactory;
use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;
use Transbank\WooCommerce\WebpayRest\Helpers\RedirectorHelper;
use Transbank\WooCommerce\WebpayRest\Helpers\SessionMessageHelper;
use Transbank\WooCommerce\WebpayRest\Telemetry\PluginVersion;
use Transbank\WooCommerce\WebpayRest\TransbankSdkWebpayRest;
use Transbank\WooCommerce\WebpayRest\TransbankWebpayOrders;
use Transbank\WooCommerce\WebpayRest\Helpers\WordpressPluginVersion;

if (!defined('ABSPATH')) {
    exit();
} // Exit if accessed directly

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @wordpress-plugin
 * Plugin Name: Transbank Webpay Plus REST
 * Plugin URI: https://www.transbankdevelopers.cl/plugin/woocommerce/webpay
 * Description: Recibe pagos en línea con Tarjetas de Crédito y Redcompra en tu WooCommerce a través de Webpay Plus.
 * Version: VERSION_REPLACE_HERE
 * Author: TransbankDevelopers
 * Author URI: https://www.transbank.cl
 * WC requires at least: 3.4.0
 * WC tested up to: 4.9.0
 */
add_action('plugins_loaded', 'woocommerce_transbank_rest_init', 0);

//todo: Eliminar todos estos require y usar PSR-4 de composer
require_once plugin_dir_path(__FILE__) . "vendor/autoload.php";
require_once plugin_dir_path(__FILE__) . "libwebpay/ConnectionCheck.php";
require_once plugin_dir_path(__FILE__) . "libwebpay/ReportGenerator.php";

register_activation_hook(__FILE__, 'transbank_webpay_rest_on_webpay_rest_plugin_activation');
add_action( 'admin_init', 'on_transbank_rest_webpay_plugins_loaded' );
add_action('wp_ajax_check_connection', 'ConnectionCheck::check');
add_action('wp_ajax_get_transaction_status', TransactionStatusController::class . '::status');
add_action('wp_ajax_download_report', \Transbank\Woocommerce\ReportGenerator::class . '::download');
add_filter('woocommerce_payment_gateways', 'woocommerce_add_transbank_gateway');
add_action('woocommerce_before_cart', function() {
    SessionMessageHelper::printMessage();
});

add_action('admin_enqueue_scripts', function() {
    wp_enqueue_style('tbk-styles', plugins_url('/css/tbk.css', __FILE__));
    wp_enqueue_script('tbk-ajax-script', plugins_url('/js/admin.js', __FILE__), ['jquery']);
    wp_enqueue_script('tbk-thickbox', plugins_url('/js/swal.min.js', __FILE__));
    wp_localize_script('tbk-ajax-script', 'ajax_object', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'  => wp_create_nonce( 'my-ajax-nonce' ),
    ]);
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'transbank_webpay_rest_add_rest_action_links');

//Start sessions if not already done
add_action('init',function() {
    if( !headers_sent() && '' == session_id() ) {
        session_start([
            'read_and_close' => true
        ]);
    }
});

function woocommerce_transbank_rest_init()
{
    if (!class_exists("WC_Payment_Gateway")) {
        return;
    }

    /**
     * @property string icon
     * @property string  method_title
     * @property string title
     * @property string description
     * @property string id
     */
    class WC_Gateway_Transbank_Webpay_Plus_REST extends WC_Payment_Gateway
    {
        private static $URL_RETURN;
        private static $URL_FINAL;

        protected $notify_url;
        protected $plugin_url;
        protected $log;
        protected $config;


        public function __construct()
        {

            self::$URL_RETURN = home_url('/') . '?wc-api=WC_Gateway_transbank_webpay_plus_rest';
            self::$URL_FINAL = home_url('/') . '?wc-api=TransbankWebpayRestThankYouPage';;

            $this->id = 'transbank_webpay_plus_rest';
            $this->icon = plugin_dir_url(__FILE__ ) . 'libwebpay/images/webpay.png';
            $this->method_title = __('Transbank Webpay Plus', 'transbank_webpay_plus_rest');
            $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_' . $this->id, home_url('/'));
            $this->title = 'Transbank Webpay Plus';
            $this->description = 'Permite el pago de productos y/o servicios, con tarjetas de crédito, débito y prepago a través de Webpay Plus';
            $this->method_description = 'Permite el pago de productos y/o servicios, con tarjetas de crédito, débito y prepago a través de Webpay Plus';
            $this->plugin_url = plugins_url('/', __FILE__);
            $this->log = new LogHandler();

            $this->supports = [
                'refunds',
            ];


            $this->config = [
                "MODO" => trim($this->get_option('webpay_rest_environment', 'TEST')),
                "COMMERCE_CODE" => trim($this->get_option('webpay_rest_commerce_code', Options::DEFAULT_COMMERCE_CODE)),
                "API_KEY" => $this->get_option('webpay_rest_api_key', Options::DEFAULT_API_KEY),
                "URL_RETURN" => home_url('/') . '?wc-api=WC_Gateway_' . $this->id,
                "ECOMMERCE" => 'woocommerce',
                "STATUS_AFTER_PAYMENT" => $this->get_option('webpay_rest_after_payment_order_status', null)
            ];

            /**
             * Carga configuración y variables de inicio
             **/

            $this->init_form_fields();
            $this->init_settings();

            add_action('woocommerce_thankyou', [new ThankYouPageController($this->config), 'show'], 1);
            add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'registerPluginVersion']);
            add_action('woocommerce_api_wc_gateway_' . $this->id, [$this, 'check_ipn_response']);

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }

        public function process_refund( $order_id, $amount = null, $reason = '' ) {
            $order = new WC_Order($order_id);
            $transaction = TransbankWebpayOrders::getApprovedByOrderId($order_id);

            if (!$transaction) {
                $order->add_order_note('Se intentó anular transacción, pero no se encontró en la base de datos de transacciones de webpay plus. ');
                return false;
            }
            $response = [];
            try {
                $sdk = new TransbankSdkWebpayRest();
                $response = $sdk->refund($transaction->token, round($amount));
                $jsonResponse = json_encode($response, JSON_PRETTY_PRINT);
            } catch (Exception $e) {
                $order->add_order_note('Error al anular: ' . $e->getMessage());
                return false;
            }

            if ($response->getType() === 'REVERSED' || ($response->getType() === 'NULLIFIED' && (int) $response->getResponseCode() === 0)) {
                $type = $response->getType() === 'REVERSED' ? 'Reversa' : 'Anulación';
                $order->add_order_note('Refund a través de Webpay ejecutado CORRECTAMENTE.' .
                    "\nTipo: " . $type .
                    "\nMonto devuelto: $" . number_format($amount, 0, ',', '.')  .
                    "\nBalance: $" . number_format($response->getBalance(), 0, ',', '.')  .
                    "\n\nRespuesta de anulación: \n" . $jsonResponse);
                return true;
            } else  {
                $order->add_order_note('Anulación a través de Webpay FALLIDA. ' .
                    "\n\n" . $jsonResponse);
                return false;
            }

            return false;

        }

        public function registerPluginVersion()
        {
            if (!$this->get_option('webpay_rest_environment', 'TEST') === 'LIVE') {
                return;
            }

            $commerceCode = $this->get_option('webpay_rest_commerce_code');
            if ($commerceCode == Options::DEFAULT_COMMERCE_CODE) {
                // If we are using the default commerce code, then abort as the user have not updated that value yet.
                return;
            };

            $pluginVersion = $this->getPluginVersion();

            (new PluginVersion)->registerVersion($commerceCode, $pluginVersion, wc()->version,
                PluginVersion::ECOMMERCE_WOOCOMMERCE);
        }

        /**
         * Comprueba configuración de moneda (Peso Chileno)
         **/
        public static function is_valid_for_use()
        {
            return in_array(get_woocommerce_currency(), ['CLP']);
        }

        /**
         * Inicializar campos de formulario
         **/
        function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Activar/Desactivar', 'transbank_webpay_plus_rest'),
                    'type' => 'checkbox',
                    'default' => 'yes'
                ),
                'webpay_rest_environment' => array(
                    'title' => __('Ambiente', 'transbank_webpay_plus_rest'),
                    'type' => 'select',
                    'description' => 'Define si el plugin operará en el ambiente de pruebas (integración) o en el
                    ambiente real (producción). Si defines el ambiente como "Integración" <strong>no</strong> se usarán el código de
                    comercio y llave secreta que tengas configurado abajo, ya que se usará el código de comercio especial del ambiente de pruebas.',
                    'options' => array(
                        'TEST' => __('Integración', 'transbank_webpay_plus_rest'),
                        'LIVE' => __('Producción', 'transbank_webpay_plus_rest')
                    ),
                    'default' => 'TEST'
                ),
                'webpay_rest_commerce_code' => array(
                    'title' => __('Código de Comercio Producción', 'transbank_webpay_plus_rest'),
                    'placeholder' => 'Ej: 597012345678',
                    'description' => 'Indica tu código de comercio para el ambiente de producción. Este se te entregará al completar el proceso de afiliación comercial. Siempre comienza con 5970 y debe tener 12 dígitos. Si el tuyo tiene 8, antepone 5970. ',
                    'type' => 'text',
                    'default' => ''
                ),
                'webpay_rest_api_key' => array(
                    'title' => __('API Key (llave secreta) producción', 'transbank_webpay_plus_rest'),
                    'type' => 'text',
                    'placeholder' => 'Ej: XXXXXXXXXXXXXXXXXXXXXXXXXXXX',
                    'description' => 'Esta llave privada te la entregará Transbank luego de que completes el proceso de validación (link más abajo). No la compartas con nadie una vez que la tengas. ',
                    'default' => ''
                )
            );
        }


        /**
         * Pagina Receptora
         **/
        function receipt_page($order_id)
        {
            $order = new WC_Order($order_id);
            $amount = (int) number_format($order->get_total(), 0, ',', '');
            $sessionId = uniqid();
            $buyOrder = $order_id;
            $returnUrl = self::$URL_RETURN;
            $finalUrl = str_replace("_URL_",
                add_query_arg('key', $order->get_order_key(), $order->get_checkout_order_received_url()),
                self::$URL_FINAL);

            $transbankSdkWebpay = new TransbankSdkWebpayRest($this->config);
            $result = $transbankSdkWebpay->createTransaction($amount, $sessionId, $buyOrder, $returnUrl);

            if (!isset($result["token_ws"])) {
                wc_add_notice( 'Ocurrió un error al intentar conectar con WebPay Plus. Por favor intenta mas tarde.<br/>',
                    'error');
                return;
            }

            $url = $result["url"];
            $token_ws = $result["token_ws"];

            TransbankWebpayOrders::createTransaction([
                'order_id' => $order_id,
                'buy_order' => $buyOrder,
                'amount' => $amount,
                'token' => $token_ws,
                'session_id' => $sessionId,
                'status' => TransbankWebpayOrders::STATUS_INITIALIZED
            ]);

            RedirectorHelper::redirect($url, ["token_ws" => $token_ws]);
        }

        /**
         * Obtiene respuesta IPN (Instant Payment Notification)
         **/
        function check_ipn_response()
        {
            @ob_clean();
            if (isset($_POST)) {
                header('HTTP/1.1 200 OK');
                return (new ResponseController($this->config))->response($_POST);
            } else {
                echo "Ocurrió un error al procesar su compra";
            }
        }

        /**
         * Procesar pago y retornar resultado
         **/
        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);

            return [
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            ];
        }

        /**
         * Opciones panel de administración
         **/
        public function admin_options()
        {
            $showedWelcome = get_site_option('transbank_webpay_rest_showed_welcome_message');
            update_site_option('transbank_webpay_rest_showed_welcome_message', true);
            $tab = 'options';
            $environment = $this->config['MODO'];
            include __DIR__ . '/views/admin/options-tabs.php';

        }

        /**
         * @return mixed
         */
        public function getPluginVersion()
        {
            return (new WordpressPluginVersion())->get();
        }


    }

    /**
     * Añadir Transbank Plus a Woocommerce
     **/
    function woocommerce_add_transbank_gateway($methods)
    {
        $methods[] = 'WC_Gateway_transbank_webpay_plus_rest';

        return $methods;
    }

    /**
     * Muestra detalle de pago a Cliente a finalizar compra
     **/
    function pay_transbank_webpay_content($orderId)
    {

    }


}

function transbank_webpay_rest_add_rest_action_links($links)
{
    $newLinks = [
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_webpay_plus_rest') . '">Configuración</a>',
    ];

    return array_merge($links, $newLinks);
}

function transbank_webpay_rest_on_webpay_rest_plugin_activation()
{
    woocommerce_transbank_rest_init();
    if (!class_exists(WC_Gateway_Transbank_Webpay_Plus_REST::class)) {
        die('Se necesita tener WooCommerce instalado y activo para poder activar este plugin');
        return;
    }
    $pluginObject = new WC_Gateway_Transbank_Webpay_Plus_REST();
    $pluginObject->registerPluginVersion();
}

function on_transbank_rest_webpay_plugins_loaded() {
    TransbankWebpayOrders::createTableIfNeeded();
}

function transbank_rest_remove_database() {
    TransbankWebpayOrders::deleteTable();
}

add_action('admin_notices', function() {

    if (!class_exists(WC_Gateway_Transbank_Webpay_Plus_REST::class)) {
        return;
    }
    if (!WC_Gateway_Transbank_Webpay_Plus_REST::is_valid_for_use()) {
        ?>
        <div class="notice notice-error">
            <p><?php _e( 'Woocommerce debe estar configurado en pesos chilenos (CLP) para habilitar Webpay', 'transbank' ); ?></p>
        </div>
        <?php
    }
});
register_uninstall_hook( __FILE__, 'transbank_rest_remove_database' );

add_action( 'add_meta_boxes', function() {
    add_meta_box( 'transbank_check_payment_status', __('Verificar estado del pago','transbank_webpay_plus_rest'), function($post) {
        $order = new WC_Order($post->ID);
        $transaction = TransbankWebpayOrders::getApprovedByOrderId($order->get_id());
        include(__DIR__ . '/views/get-status.php');
    }, 'shop_order', 'side', 'core' );
});

add_action('admin_menu', function() {
    //create new top-level menu
    add_submenu_page('woocommerce', 'Configuración de Webpay Plus', 'Webpay Plus', 'administrator', 'transbank_webpay_plus_rest', function() {

        $tab = filter_input(INPUT_GET, 'tbk_tab', FILTER_SANITIZE_STRING);
        if (!in_array($tab, ['healthcheck', 'logs', 'phpinfo'])) {
            wp_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_webpay_plus_rest&tbk_tab=options'));
        }

        $healthcheck = HealthCheckFactory::create();
        $datos_hc = json_decode($healthcheck->printFullResume());
        $log = new LogHandler();

        include __DIR__ . '/views/admin/options-tabs.php';
    } , null );

});
