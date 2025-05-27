<?php

namespace Transbank\WooCommerce\WebpayRest\PaymentGateways;

use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\Webpay\WebpayPlus;
use Transbank\WooCommerce\WebpayRest\Controllers\CommitWebpayController;
use Transbank\WooCommerce\WebpayRest\Controllers\CreateWebpayController;
use Transbank\WooCommerce\WebpayRest\Controllers\RefundWebpayController;
use Transbank\WooCommerce\WebpayRest\Controllers\ThankYouPageController;
use Transbank\Plugin\Helpers\BuyOrderHelper;
use Transbank\WooCommerce\WebpayRest\WebpayplusTransbankSdk;
use WC_Order;
use WC_Payment_Gateway;

class WC_Gateway_Transbank_Webpay_Plus_REST extends WC_Payment_Gateway
{
    const ID = 'transbank_webpay_plus_rest';
    const WOOCOMMERCE_API_SLUG = 'wc_gateway_transbank_webpay_plus_rest';

    const PAYMENT_GW_DESCRIPTION = 'Permite el pago de productos y/o servicios, ' .
        'con tarjetas de crédito, débito y prepago a través de Webpay Plus';


    protected $plugin_url;
    protected $log;
    protected $config;

    /**
     * @var WebpayplusTransbankSdk
     */
    protected $webpayplusTransbankSdk;

    public function __construct()
    {
        $this->webpayplusTransbankSdk = TbkFactory::createWebpayplusTransbankSdk();
        $this->id = self::ID;
        $this->icon = plugin_dir_url(dirname(dirname(__FILE__))) . 'images/webpay.png';
        $this->method_title = __('Transbank Webpay Plus', 'transbank_webpay_plus_rest');
        $this->title = 'Webpay Plus';
        $this->description = $this->get_option('webpay_rest_payment_gateway_description', self::PAYMENT_GW_DESCRIPTION);
        $this->method_description =
            $this->get_option('webpay_rest_payment_gateway_description', self::PAYMENT_GW_DESCRIPTION);

        $this->plugin_url = plugins_url('/', __FILE__);
        $this->log = TbkFactory::createLogger();

        $this->supports = [
            'products',
            'refunds',
        ];

        $this->config = [
            'MODO' => trim($this->get_option('webpay_rest_environment', 'TEST')),
            'COMMERCE_CODE' => trim(
                $this->get_option('webpay_rest_commerce_code', WebpayPlus::INTEGRATION_COMMERCE_CODE)
            ),
            'API_KEY' => $this->get_option('webpay_rest_api_key', WebpayPlus::INTEGRATION_API_KEY),
            'ECOMMERCE' => 'woocommerce',
            'STATUS_AFTER_PAYMENT' => $this->get_option('webpay_rest_after_payment_order_status', ''),
        ];

        /**
         * Carga configuración y variables de inicio.
         **/
        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_thankyou', [new ThankYouPageController(), 'show'], 1);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_wc_gateway_' . $this->id, [$this, 'check_ipn_response']);

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
        return (new RefundWebpayController())->proccess(
            $order_id,
            $amount,
            $reason
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
        $environmentDescription = 'Define si el plugin operará en el ambiente de pruebas (integración) o en el ' .
            'ambiente real (producción). <br/><br/>Si defines el ambiente como "Integración" <strong>no</strong> ' .
            'se usarán el código de comercio y llave secreta que tengas configurado abajo, ya que se usará el código ' .
            'de comercio especial del ambiente de pruebas.';

        $commerceCodeDescription = 'Indica tu código de comercio para el ambiente de producción. <br/><br/>' .
            'Este se te entregará al completar el proceso de afiliación comercial. <br /><br />' .
            'Siempre comienza con 5970 y debe tener 12 dígitos. Si el tuyo tiene 8, antepone 5970.';

        $apiKeyDescription = 'Esta llave privada te la entregará Transbank luego de que completes el proceso ' .
            'de validación (link más abajo).<br/><br/>No la compartas con nadie una vez que la tengas. ';

        $buyOrderDescription = 'Define un formato personalizado para la orden de compra asociada a la transacción en
            Transbank, lo que permite identificarla fácilmente dentro del sistema de Transbank.';

        $this->form_fields = [
            'enabled' => [
                'title' => __('Activo', 'transbank_webpay_plus_rest'),
                'type' => 'checkbox',
                'label' => " ",
                'default' => 'yes',
            ],
            'webpay_rest_environment' => [
                'title' => __('Ambiente', 'transbank_webpay_plus_rest'),
                'type' => 'select',
                'desc_tip' => $environmentDescription,
                'options' => [
                    'TEST' => __('Integración', 'transbank_webpay_plus_rest'),
                    'LIVE' => __('Producción', 'transbank_webpay_plus_rest'),
                ],
                'default' => 'TEST',
            ],
            'webpay_rest_commerce_code' => [
                'title' => __('Código de Comercio Producción', 'transbank_webpay_plus_rest'),
                'placeholder' => 'Ej: 597012345678',
                'desc_tip' => $commerceCodeDescription,
                'type' => 'text',
                'default' => '',
            ],
            'webpay_rest_api_key' => [
                'title' => __('API Key (llave secreta) producción', 'transbank_webpay_plus_rest'),
                'type' => 'password',
                'placeholder' => 'Ej: XXXXXXXXXXXXXXXXXXXXXXXXXXXX',
                'desc_tip' => $apiKeyDescription,
                'default' => '',
            ],
            'webpay_rest_after_payment_order_status' => [
                'title' => __('Order Status', 'transbank_webpay_plus_rest'),
                'type' => 'select',
                'desc_tip' => 'Define el estado de la orden luego del pago exitoso.',
                'options' => [
                    '' => 'Default',
                    'processing' => 'Processing',
                    'completed' => 'Completed',
                ],
                'default' => '',
            ],
            'webpay_rest_payment_gateway_description' => [
                'title' => __('Descripción', 'transbank_webpay_plus_rest'),
                'type' => 'textarea',
                'desc_tip' => 'Define la descripción del medio de pago.',
                'default' => self::PAYMENT_GW_DESCRIPTION,
                'class' => 'admin-textarea'
            ],
            'buy_order_format' => [
                'title'       => __('Formato de orden de compra', 'transbank_wc_plugin'),
                'placeholder' => 'Ej: ' . WebpayplusTransbankSdk::BUY_ORDER_FORMAT,
                'desc_tip'    => $buyOrderDescription,
                'type'        => 'text',
                'default' => WebpayplusTransbankSdk::BUY_ORDER_FORMAT
            ]
        ];
    }

    /**
     * Obtiene respuesta IPN (Instant Payment Notification).
     **/
    public function check_ipn_response()
    {
        ob_clean();
        header('HTTP/1.1 200 OK');
        return (new CommitWebpayController())->proccess();
    }

    /**
     * Procesar pago y retornar resultado.
     **/
    public function process_payment($order_id)
    {
        return (new CreateWebpayController())->proccess($this->id, static::WOOCOMMERCE_API_SLUG, $order_id);
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
        include_once __DIR__ . '/../../views/admin/options-tabs.php';
    }

    public function process_admin_options() {
        $buyOrderFormat = isset($_POST[$this->get_field_key('buy_order_format')])
            ? wc_clean(wp_unslash($_POST[$this->get_field_key('buy_order_format')])): '';

        if (!BuyOrderHelper::isValidFormat($buyOrderFormat)) {
            \WC_Admin_Settings::add_error(__("El formato personalizado de orden de compra no es válido.",
            'woocommerce'));
        }
        else  {
            parent::process_admin_options();
        }
    }
}
