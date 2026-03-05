<?php

namespace Transbank\WooCommerce\WebpayRest\PaymentGateways;

use Transbank\Plugin\Exceptions\EcommerceException;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\Webpay\Options;
use Transbank\WooCommerce\WebpayRest\Controllers\FinishOneclickController;
use Transbank\Plugin\Helpers\BuyOrderHelper;
use Transbank\WooCommerce\WebpayRest\Repositories\InscriptionRepository;
use Transbank\WooCommerce\WebpayRest\Tokenization\WC_Payment_Token_Oneclick;
use Transbank\Webpay\Oneclick\Exceptions\MallTransactionAuthorizeException;
use Transbank\Webpay\Oneclick\Exceptions\InscriptionStartException;
use Transbank\WooCommerce\WebpayRest\Config\TransbankConfig;
use Transbank\WooCommerce\WebpayRest\Config\TransbankGatewayIds;
use Transbank\WooCommerce\WebpayRest\Config\TransbankGatewaySettings;
use Transbank\WooCommerce\WebpayRest\Controllers\AuthorizeOneclickController;
use Transbank\WooCommerce\WebpayRest\Controllers\ScheduledAuthorizeOneclickController;
use Transbank\WooCommerce\WebpayRest\Controllers\RefundOneclickController;
use Transbank\WooCommerce\WebpayRest\Controllers\StartOneclickController;
use Transbank\WooCommerce\WebpayRest\Services\OneclickAuthorizationService;

use WC_Order;
use WC_Payment_Gateway_CC;

/**
 * Class WC_Gateway_Transbank_Oneclick_Mall_REST.
 */
class WC_Gateway_Transbank_Oneclick_Mall_REST extends WC_Payment_Gateway_CC
{
    use TransbankRESTPaymentGateway;

    const ID = TransbankGatewayIds::ONECLICK_MALL_REST;
    const WOOCOMMERCE_API_RETURN_ADD_PAYMENT = 'wc_gateway_transbank_oneclick_return_payments';

    const PAYMENT_GW_DESCRIPTION = 'Inscribe tu tarjeta de crédito, débito o prepago y luego paga ' .
        'con un solo click a través de Webpay Oneclick';

    protected $logger;

    /**
     * Indicates if the exception message should be displayed in the notice when checkout block is enabled.
     *
     * @var bool
     */
    private $shouldThrowException;
    private $gatewaySettings;

    /** @var \Transbank\WooCommerce\WebpayRest\Services\OneclickInscriptionService */
    private $inscriptionService;

    /**
     * WC_Gateway_Transbank_Oneclick_Mall_REST constructor.
     */
    public function __construct()
    {
        $this->gatewaySettings = TransbankConfig::oneclickMall();
        $this->supports = [
            'refunds',
            'tokenization',
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            // 'subscription_payment_method_change',
            // 'subscription_payment_method_change_customer',
            // 'subscription_payment_method_change_admin',
            'multiple_subscriptions',
        ];

        $this->id = self::ID;
        $this->title = 'Webpay Oneclick';
        $this->method_title = 'Webpay Oneclick';
        $this->description = $this->description = $this->gatewaySettings->get(
            $this->gatewaySettings::DESCRIPTION,
            self::PAYMENT_GW_DESCRIPTION
        );
        $this->method_description = $this->description = $this->gatewaySettings->get(
            $this->gatewaySettings::DESCRIPTION,
            self::PAYMENT_GW_DESCRIPTION
        );

        $this->icon = plugin_dir_url(dirname(dirname(__FILE__))) . 'images/oneclick.png';
        $this->shouldThrowException = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->logger = TbkFactory::createOneclickLogger();
        $this->inscriptionService = TbkFactory::createOneclickInscriptionService();

        $this->max_amount = $this->get_option('max_amount') ?? 100000;

        add_action(
            'woocommerce_scheduled_subscription_payment_' . $this->id,
            [$this, 'scheduled_subscription_payment'],
            10,
            3
        );
        add_action('woocommerce_api_' . strtolower(static::WOOCOMMERCE_API_RETURN_ADD_PAYMENT), [
            new FinishOneclickController($this->id),
            'process',
        ]);

        add_action('woocommerce_payment_token_deleted', [$this, 'on_payment_token_deleted'], 10, 1);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_filter('woocommerce_payment_methods_list_item', [$this, 'methods_list_item_oneclick'], null, 2);
        add_filter('woocommerce_payment_token_class', [$this, 'getOneclickPaymentTokenClass']);
        add_filter('woocommerce_saved_payment_methods_list', [$this, 'get_saved_payment_methods_list'], 10, 2);
    }

    /**
     * Procesar pago y retornar resultado.
     **
     *
     * @throws MallTransactionAuthorizeException
     */
    public function process_payment($order_id)
    {
        $order = new WC_Order($order_id);
        $returnUrl = $this->get_return_url($order);
        return (new AuthorizeOneclickController($this->id, $returnUrl))->process($order_id);
    }

    /**
     * Processes a scheduled subscription payment.
     *
     * This method authorizes a scheduled subscription payment for the given renewal order. It retrieves the customer ID
     * from the renewal order, obtains the customer's default payment token and authorizes the payment with Oneclick.
     *
     * @param float $amount_to_charge The amount to charge for the subscription payment.
     * @param WC_Order $renewalOrder The renewal order object for the subscription.
     *
     * @throws EcommerceException If there is no customer ID on the renewal order.
     */
    public function scheduled_subscription_payment($amount_to_charge, WC_Order $renewalOrder)
    {
        (new ScheduledAuthorizeOneclickController())->process(
            $amount_to_charge,
            $renewalOrder
        );
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        return (new RefundOneclickController())->process($order_id, $amount, $reason);
    }

    public function payment_fields()
    {
        $description = $this->get_description();
        if ($description) {
            echo wpautop(wptexturize($description)); // @codingStandardsIgnoreLine.
        }
        parent::payment_fields();
    }

    public function admin_options()
    {
        if ($this->is_valid_for_use()) {
            $pluginSettings = TransbankConfig::plugin();
            $showedWelcome = $pluginSettings->isWelcomeMessageShown(self::ID);

            if (!$showedWelcome) {
                $pluginSettings->setWelcomeMessageShown(self::ID, true);
            }

            $tab = 'options_oneclick';
            $environment = $this->gatewaySettings->get(
                TransbankGatewaySettings::ENVIRONMENT
            );
            include_once __DIR__ . '/../../views/admin/options-tabs.php';
        } else {
?>
            <div class="inline error">
                <p>
                    <strong><?php esc_html_e(
                                'Gateway disabled',
                                'woocommerce'
                            ); ?></strong>: <?php esc_html_e(
                                                'Oneclick no soporta la moneda configurada en tu tienda. ' .
                                                    'Solo soporta CLP',
                                                'transbank_wc_plugin'
                                            ); ?>
                </p>
            </div>
<?php
        }
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws InscriptionStartException
     */
    public function add_payment_method()
    {
        return (new StartOneclickController())->process();
    }

    /**
     * Outputs a checkbox for saving a new payment method to the database.
     *
     * @since 2.6.0
     */
    public function save_payment_method_checkbox()
    {
        $html = '<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
            <strong>Esta tarjeta se guardará en tu cuenta para que puedas volver a usarla.</strong>
            </p>';
        echo $html;
    }

    public function is_available()
    {
        if (!$this->is_valid_for_use()) {
            return false;
        }

        return parent::is_available();
    }

    public function form()
    {
        // No render payment form.
    }

    public function get_saved_payment_methods_list($saved_methods)
    {
        $pluginEnvironment = $this->get_option('environment');
        $oneclickCards = $saved_methods['oneclick'] ?? [];
        $filteredCards = [];

        foreach ($oneclickCards as $card) {
            if ($card['method']['environment'] === $pluginEnvironment) {
                $filteredCards[] = $card;
            }
        }

        if (count($oneclickCards) > 0) {
            $saved_methods['oneclick'] = $filteredCards;
        }

        return $saved_methods;
    }

    public function methods_list_item_oneclick($item, $payment_token)
    {
        if ('oneclick' !== strtolower($payment_token->get_type())) {
            return $item;
        }

        $cardEnvironment = $payment_token->get_environment();
        $environmentSuffix = $cardEnvironment === Options::ENVIRONMENT_INTEGRATION ? ' [Test]' : '';

        $item['method']['last4'] = $payment_token->get_last4() . $environmentSuffix;
        $item['method']['brand'] = $payment_token->get_card_type();
        $item['method']['environment'] = $cardEnvironment;

        return $item;
    }

    public function on_payment_token_deleted($token_id)
    {
        $inscription = null;
        $this->logger->logInfo('Iniciando ejecución de hook on_payment_token_deleted', ['token_id' => $token_id]);
        try {
            $inscription = $this->inscriptionService->deleteByPaymentTokenId((int) $token_id);
            $this->logger->logInfo('Inscripción Oneclick eliminada asociada al token de pago', [
                'token_id' => $token_id,
                'inscription_id' => $inscription->id,
                'user_id' => $inscription->userId,
                'username' => $inscription->username,
                'email' => $inscription->email,
                'environment' => $inscription->environment,
            ]);

            $this->logger->logInfo('ejecución de hook on_payment_token_deleted finalizada correctamente.', [
                'token_id' => $token_id,
                'inscription_id' => $inscription?->id,
            ]);
        } catch (\Throwable $e) {
            $this->logger->logError('ejecución de hook on_payment_token_deleted fallida.', [
                'token_id' => $token_id,
                'user_id' => $inscription?->userId,
                'username' => $inscription?->username,
                'email' => $inscription?->email,
                'environment' => $inscription?->environment,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Inicializar campos de formulario.
     **/
    public function init_form_fields()
    {
        $gatewaySettings = $this->gatewaySettings;
        $environmentDescription = 'Define si el plugin operará en el ambiente de pruebas (integración) o en el ' .
            'ambiente real (producción). <br/><br/>Si defines el ambiente como "Integración" <strong>no</strong> ' .
            'se usarán el código de comercio y llave secreta que tengas configurado abajo, ya que se usará el código ' .
            'de comercio especial del ambiente de pruebas.';

        $commerceCodeDescription = 'Ingresa tu código de comercio Mall para el ambiente de producción. <br/><br/>' .
            'Este se te entregará al completar el proceso de afiliación comercial. <br/><br/>' .
            'Siempre comienza con 5970 y debe tener 12 dígitos. Si el tuyo tiene 8, antepone 5970. ';

        $childCommerceCodeDescription = 'Indica tu código de comercio Tienda para el ambiente de producción.' .
            '<br/><br/>Este se te entregará al completar el proceso de afiliación comercial. <br /><br />' .
            'Siempre comienza con 5970 y debe tener 12 dígitos. Si el tuyo tiene 8, antepone 5970.';

        $apiKeyDescription = 'Esta llave privada te la entregará Transbank luego de que completes el proceso ' .
            'de validación (link más abajo).<br/><br/>No la compartas con nadie una vez que la tengas.';

        $maxAmountDescription = 'Define el monto máximo que un cliente puede pagar con Oneclick.<br/><br/>' .
            'Si un cliente va a realizar una compra superior a este monto, Oneclick no aparecerá como opción de ' .
            'pago en el proceso de checkout. Dejar en 0 si no se desea tener un límite (no recomendado). <br/><br/>' .
            'Recuerda que en Oneclick, al no contar con autentificación bancaria, es tu comercio el que asume el ' .
            'riesgo en caso de fraude o contracargo. <br/><br />Independiente del monto que definas en esta ' .
            'configuración, en tu contrato de Oneclick, existe un límite de cantidad de transacciones diarias, ' .
            'un monto máximo por transacción y monto acumulado diario. Si un cliente supera ese límite, ' .
            'su transacción será rechazada.';

        $buyOrderDescription = 'Define un formato personalizado para la orden de compra principal asociada a la
            transacción en Transbank. Esta orden identifica la transacción de manera única en el sistema de Transbank.';

        $childBuyOrderDescription = 'Define un formato personalizado para la orden de compra hija, utilizada en
            transacciones con múltiples tiendas. Permite identificar individualmente cada subtransacción
            dentro del sistema de Transbank.';


        $this->form_fields = [
            $gatewaySettings::ENABLED => [
                'title' => __('Activo', 'transbank_wc_plugin'),
                'type' => 'checkbox',
                'label' => " ",
                'default' => 'no',
            ],
            $gatewaySettings::ENVIRONMENT => [
                'title' => __('Ambiente', 'transbank_wc_plugin'),
                'type' => 'select',
                'desc_tip' => $environmentDescription,
                'options' => [
                    Options::ENVIRONMENT_INTEGRATION => __('Integración', 'transbank_wc_plugin'),
                    Options::ENVIRONMENT_PRODUCTION => __('Producción', 'transbank_wc_plugin'),
                ],
                'default' => Options::ENVIRONMENT_INTEGRATION,
            ],
            $gatewaySettings::COMMERCE_CODE => [
                'title' => __('Código de Comercio Mall Producción', 'transbank_wc_plugin'),
                'placeholder' => 'Ej: 597012345678',
                'desc_tip' => $commerceCodeDescription,
                'type' => 'text',
                'default' => '',
            ],
            $gatewaySettings::CHILD_COMMERCE_CODE => [
                'title' => __('Código de Comercio Tienda Producción', 'transbank_wc_plugin'),
                'placeholder' => 'Ej: 597012345678',
                'desc_tip' => $childCommerceCodeDescription,
                'type' => 'text',
                'default' => '',
            ],
            $gatewaySettings::API_KEY => [
                'title' => __('API Key (llave secreta) producción', 'transbank_wc_plugin'),
                'type' => 'password',
                'placeholder' => 'Ej: XXXXXXXXXXXXXXXXXXXXXXXXXXXX',
                'desc_tip' => $apiKeyDescription,
                'default' => '',
            ],
            $gatewaySettings::MAX_AMOUNT => [
                'title' => __('Monto máximo de transacción permitido', 'transbank_wc_plugin'),
                'type' => 'number',
                'options' => ['step' => 100],
                'default' => '0',
                'desc_tip' => $maxAmountDescription,
            ],
            $gatewaySettings::AFTER_PAYMENT_ORDER_STATUS => [
                'title' => __('Order Status', 'transbank_wc_plugin'),
                'type' => 'select',
                'desc_tip' => 'Define el estado de la orden luego del pago exitoso.',
                'options' => [
                    '' => 'Default',
                    'processing' => 'Processing',
                    'completed' => 'Completed',
                ],
                'default' => '',
            ],
            $gatewaySettings::DESCRIPTION => [
                'title' => __('Descripción', 'transbank_wc_plugin'),
                'type' => 'textarea',
                'desc_tip' => 'Define la descripción del medio de pago.',
                'default' => self::PAYMENT_GW_DESCRIPTION,
                'class' => 'admin-textarea'
            ],
            $gatewaySettings::BUY_ORDER_FORMAT => [
                'title' => __(
                    'Formato personalizado de orden de compra principal',
                    'transbank_wc_plugin'
                ),
                'placeholder' => 'Ej: ' . OneclickAuthorizationService::BUY_ORDER_FORMAT,
                'desc_tip' => $buyOrderDescription,
                'type' => 'text',
                'default' => OneclickAuthorizationService::BUY_ORDER_FORMAT
            ],
            $gatewaySettings::CHILD_BUY_ORDER_FORMAT => [
                'title' => __('Formato personalizado de orden de compra hija', 'transbank_wc_plugin'),
                'placeholder' => 'Ej: ' . OneclickAuthorizationService::CHILD_BUY_ORDER_FORMAT,
                'desc_tip' => $childBuyOrderDescription,
                'type' => 'text',
                'default' => OneclickAuthorizationService::CHILD_BUY_ORDER_FORMAT
            ]
        ];
    }

    public function getOneclickPaymentTokenClass()
    {
        return WC_Payment_Token_Oneclick::class;
    }

    public function is_valid_for_use()
    {
        return in_array(
            get_woocommerce_currency(),
            apply_filters('woocommerce_transbank_webpay_oneclick_supported_currencies', ['CLP']),
            true
        );
    }

    public function process_admin_options()
    {
        $buyOrderFormat = isset($_POST[$this->get_field_key('buy_order_format')])
            ? wc_clean(wp_unslash($_POST[$this->get_field_key('buy_order_format')])) : '';
        $childBuyOrderFormat = isset($_POST[$this->get_field_key('child_buy_order_format')])
            ? wc_clean(wp_unslash($_POST[$this->get_field_key('child_buy_order_format')])) : '';

        $isValid = true;

        if (!BuyOrderHelper::isValidFormat($buyOrderFormat)) {
            \WC_Admin_Settings::add_error(__(
                "El formato personalizado de orden de compra principal no es válido.",
                'woocommerce'
            ));
            $isValid = false;
        }

        if (!BuyOrderHelper::isValidFormat($childBuyOrderFormat)) {
            \WC_Admin_Settings::add_error(__(
                "El formato personalizado de orden de compra hija no es válido.",
                'woocommerce'
            ));
            $isValid = false;
        }
        if ($isValid) {
            parent::process_admin_options();
        }
    }
}
