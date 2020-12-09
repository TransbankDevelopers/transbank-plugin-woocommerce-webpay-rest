<?php
use Transbank\Webpay\Options;
use Transbank\WooCommerce\WebpayRest\Controllers\ResponseController;
use Transbank\WooCommerce\WebpayRest\Controllers\ThankYouPageController;
use Transbank\WooCommerce\WebpayRest\Helpers\HealthCheck;
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
 * Author: Transbank
 * Author URI: https://www.transbank.cl
 * WC requires at least: 3.4.0
 * WC tested up to: 4.0.1
 */
add_action('plugins_loaded', 'woocommerce_transbank_rest_init', 0);

//todo: Eliminar todos estos require y usar PSR-4 de composer
require_once plugin_dir_path(__FILE__) . "vendor/autoload.php";
require_once plugin_dir_path(__FILE__) . "libwebpay/ConnectionCheck.php";
require_once plugin_dir_path(__FILE__) . "libwebpay/ReportGenerator.php";

register_activation_hook(__FILE__, 'on_webpay_rest_plugin_activation');
add_action( 'admin_init', 'on_transbank_rest_webpay_plugins_loaded' );
add_action('wp_ajax_check_connection', 'ConnectionCheck::check');
add_action('wp_ajax_download_report', 'Transbank\Woocommerce\ReportGenerator::download');
add_filter('woocommerce_payment_gateways', 'woocommerce_add_transbank_gateway');
add_action('woocommerce_before_cart', function() {
    SessionMessageHelper::printMessage();
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_rest_action_links');

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
            $this->method_title = __('Transbank Webpay Plus');
            $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_' . $this->id, home_url('/'));
            $this->title = 'Transbank Webpay Plus';
            $this->description = 'Permite el pago de productos y/o servicios, con tarjetas de crédito y Redcompra a través de Webpay Plus';
            $this->plugin_url = plugins_url('/', __FILE__);
            $this->log = new LogHandler();


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
            add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }
        public function enqueueScripts()
        {
            wp_enqueue_script('ajax-script', plugins_url('/js/admin.js', __FILE__), ['jquery']);
            wp_localize_script('ajax-script', 'ajax_object', ['ajax_url' => admin_url('admin-ajax.php')]);
        }

        public function checkConnection()
        {
            require_once('ConfigProvider.php');
            require_once('HealthCheck.php');

            $configProvider = new ConfigProvider();
            $config = [
                'MODO' => $configProvider->getConfig('webpay_rest_test_mode'),
                'COMMERCE_CODE' => $configProvider->getConfig('webpay_rest_commerce_code'),
                'API_KEY' => $configProvider->getConfig('webpay_rest_api_key'),
                'ECOMMERCE' => 'woocommerce'
            ];
            $healthcheck = new HealthCheck($config);
            $resp = $healthcheck->setInitTransaction();
            // ob_clean();
            echo json_encode($resp);
            exit;
        }

        public function registerPluginVersion()
        {
            if (!$this->get_option('webpay_rest_test_mode', 'INTEGRACION') === 'PRODUCCION') {
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
                    'options' => array(
                        'TEST' => __('Integración', 'transbank_webpay_plus_rest'),
                        'LIVE' => __('Producción', 'transbank_webpay_plus_rest')
                    ),
                    'default' => 'TEST'
                ),
                'webpay_rest_commerce_code' => array(
                    'title' => __('Código de Comercio', 'transbank_webpay_plus_rest'),
                    'type' => 'text',
                    'default' => $this->config['COMMERCE_CODE']
                ),
                'webpay_rest_api_key' => array(
                    'title' => __('API Key', 'transbank_webpay_plus_rest'),
                    'type' => 'text',
                    'default' => $this->config['API_KEY']
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
                echo "Ocurrio un error al procesar su compra";
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

            $this->healthcheck = new HealthCheck($this->config);
            $datos_hc = json_decode($this->healthcheck->printFullResume());
            include 'libwebpay/admin-options.php';
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

function add_rest_action_links($links)
{
    $newLinks = [
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=transbank_webpay_plus_rest') . '">Configuración</a>',
    ];

    return array_merge($links, $newLinks);
}

function on_webpay_rest_plugin_activation()
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
