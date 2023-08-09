<?php

namespace Transbank\WooCommerce\WebpayRest\PaymentGateways;

use Exception;
use Transbank\Webpay\WebpayPlus;
use Transbank\WooCommerce\WebpayRest\Controllers\ResponseController;
use Transbank\WooCommerce\WebpayRest\Controllers\ThankYouPageController;
use Transbank\WooCommerce\WebpayRest\Helpers\ErrorHelper;
use Transbank\WooCommerce\WebpayRest\Telemetry\PluginVersion;
use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;
use Transbank\WooCommerce\WebpayRest\Helpers\WordpressPluginVersion;
use Transbank\WooCommerce\WebpayRest\PaymentGateways\TransbankRESTPaymentGateway;
use Transbank\WooCommerce\WebpayRest\WebpayplusTransbankSdk;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\CreateWebpayException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\CreateTransactionWebpayException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\GetTransactionWebpayException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\NotFoundTransactionWebpayException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\RefundWebpayException;
use Transbank\WooCommerce\WebpayRest\Exceptions\Webpay\RejectedRefundWebpayException;
use WC_Order;
use WC_Payment_Gateway;

/**
 * @property string icon
 * @property string  method_title
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

    /**
     * @var WebpayplusTransbankSdk
     */
    protected $webpayplusTransbankSdk;

    public function __construct()
    {
        self::$URL_FINAL = home_url('/').'?wc-api=TransbankWebpayRestThankYouPage';
        $this->webpayplusTransbankSdk = new WebpayplusTransbankSdk(get_option('webpay_rest_environment'), get_option('webpay_rest_commerce_code'), get_option('webpay_rest_api_key'));
        $this->id = 'transbank_webpay_plus_rest';
        $this->icon = plugin_dir_url(dirname(dirname(__FILE__))).'images/webpay.png';
        $this->method_title = __('Transbank Webpay Plus', 'transbank_webpay_plus_rest');
        $this->title = 'Transbank Webpay Plus';
        $this->description = 'Permite el pago de productos y/o servicios, con tarjetas de crédito, débito y prepago a través de Webpay Plus';
        $this->method_description = 'Permite el pago de productos y/o servicios, con tarjetas de crédito, débito y prepago a través de Webpay Plus';
        $this->plugin_url = plugins_url('/', __FILE__);
        $this->log = new LogHandler();

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

    /**
	 * Process refund.
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund.
	 * a passed in amount.
	 *
	 * @param  int        $order_id Order ID.
	 * @param  float|null $amount Refund amount.
	 * @param  string     $reason Refund reason.
	 * @return boolean True or false based on success, or a WP_Error object.
	 */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = null;
        try {
            $order = new WC_Order($order_id);
            $resp = $this->webpayplusTransbankSdk->refundTransaction($order->get_id(), round($amount));
            $refundResponse = $resp['refundResponse'];
            $transaction = $resp['transaction'];
            $jsonResponse = json_encode($refundResponse, JSON_PRETTY_PRINT);
            $this->addRefundOrderNote($refundResponse, $order, $amount, $jsonResponse);
            do_action('transbank_webpay_plus_refund_completed', $order, $transaction, $jsonResponse);
            return true;
        } catch (GetTransactionWebpayException $e) {
            $this->processRefundError($order, $e, 'transbank_webpay_plus_refund_failed', null, null);
        } catch (NotFoundTransactionWebpayException $e) {
            $this->processRefundError($order, $e, 'transbank_webpay_plus_refund_transaction_not_found', null, null);
        } catch (RefundWebpayException $e) {
            $this->processRefundError($order, $e, 'transbank_webpay_plus_refund_failed', $e->getTransaction(), null);
        }catch (RejectedRefundWebpayException $e) {
            $this->processRefundError($order, $e, 'transbank_webpay_plus_refund_failed', $e->getTransaction(), $e->getRefundResponse());
        } catch (Exception $e) {
            $this->processRefundError($order, $e, 'transbank_webpay_plus_refund_failed', null, null);
        }
    }

    private function processRefundError($order, $exception, $action, $tx, $response){
        $messageError = '<strong>Error en el reembolso:</strong><br />';
        $messageError = $messageError.$exception->getMessage();
        if (isset($response))
        {
            $messageError = $messageError."\n\n".json_encode($exception->getRefundResponse(), JSON_PRETTY_PRINT);
        }
        $order->add_order_note($messageError);
        do_action($action, $order, $tx, $exception->getMessage());
        throw new Exception($messageError);
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
            ]
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
        try {
            $order = new WC_Order($order_id);
            do_action('transbank_webpay_plus_starting_transaction', $order);
            $amount = (int) number_format($order->get_total(), 0, ',', '');
            $returnUrl = add_query_arg('wc-api', static::WOOCOMMERCE_API_SLUG, home_url('/'));
            $createResponse = $this->webpayplusTransbankSdk->createTransaction($order->get_id(), $amount, $returnUrl);
            do_action('transbank_webpay_plus_transaction_started', $order, $createResponse->token);
            return [
                'result'   => 'success',
                'redirect' => $createResponse->url.'?token_ws='.$createResponse->token,
            ];
        } catch (CreateWebpayException $e) {
            if (ErrorHelper::isGuzzleError($e)){
                wc_add_notice(ErrorHelper::getGuzzleError(), 'error');
                return;
            }
            wc_add_notice('Ocurrió un error al intentar conectar con WebPay Plus. Por favor intenta mas tarde.<br/>', 'error');
            return;
        } catch (CreateTransactionWebpayException $e) {
            throw new \Exception($e->getMessage());
        } catch (Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Opciones panel de administración.
     **/
    public function admin_options()
    {
        $showedWelcome = get_site_option('transbank_webpay_rest_showed_welcome_message');
        update_site_option('transbank_webpay_rest_showed_welcome_message', true);
        $tab = 'options';
        $environment = $this->config['MODO'];
        include_once __DIR__.'/../../views/admin/options-tabs.php';
    }

    /**
     * @return mixed
     */
    public function getPluginVersion()
    {
        return (new WordpressPluginVersion())->get();
    }
}
