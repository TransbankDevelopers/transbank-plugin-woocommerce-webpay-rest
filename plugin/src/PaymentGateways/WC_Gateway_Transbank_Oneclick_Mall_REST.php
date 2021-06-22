<?php
namespace Transbank\WooCommerce\WebpayRest\PaymentGateways;

use Transbank\Webpay\Exceptions\WebpayRequestException;
use Transbank\Webpay\Oneclick;
use Transbank\Webpay\Options;
use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;
use Transbank\WooCommerce\WebpayRest\TransbankWebpayOrders;
use WC_Order;
use WC_Payment_Token_Oneclick;
use WC_Payment_Gateway_CC;
use WC_Payment_Tokens;

class WC_Gateway_Transbank_Oneclick_Mall_REST extends WC_Payment_Gateway_CC
{
    const WOOCOMMERCE_API_RETURN_ADD_PAYMENT = 'wc_gateway_transbank_oneclick_return_payments';
    const WOOCOMMERCE_API_RETURN_ADD_PAYMENT_CHECKOUT = 'wc_gateway_transbank_oneclick_return_payment_checkout';
    /**
     * @var Oneclick\MallInscription
     */
    protected $oneclickInscription;
    /**
     * @var Oneclick\MallTransaction
     */
    protected $oneclickTransaction;
    /**
     * @var LogHandler
     */
    protected $logger;

    /**
     * WC_Gateway_Transbank_Oneclick_Mall_REST constructor.
     */
    public function __construct()
    {
        $this->supports = [
            'tokenization',
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'multiple_subscriptions'
        ];

        $this->title = 'Webpay Oneclick';
        $this->method_title = 'Webpay Oneclick';
        $this->description = 'Permite el pago de productos y/o servicios, con tarjetas de crédito, débito y prepago a través de Webpay Oneclick';
        $this->method_description = 'Permite el pago de productos y/o servicios, con tarjetas de crédito, débito y prepago a través de Webpay Oneclick';
        $this->icon = plugin_dir_url(dirname(dirname(__FILE__))) . 'libwebpay/images/oneclick.svg';
        $this->id = 'transbank_oneclick_mall_rest';


        $this->init_form_fields();
        $this->init_settings();

        $this->oneclickInscription = new Oneclick\MallInscription();
        $this->oneclickTransaction = new Oneclick\MallTransaction();
        $this->logger = (new LogHandler());

        $this->max_amount = $this->get_option('max_amount') ?? 100000;
        $environment = $this->get_option('environment');
        if ($environment === Options::ENVIRONMENT_PRODUCTION) {
            $this->oneclickInscription->configureForProduction(
                $this->get_option('commerce_code'),
                $this->get_option('api_key'));

            $this->oneclickTransaction->configureForProduction(
                $this->get_option('commerce_code'),
                $this->get_option('api_key'));

        }

        add_action('woocommerce_api_' . strtolower(static::WOOCOMMERCE_API_RETURN_ADD_PAYMENT), [$this, 'process_inscription_return']);
        add_action('woocommerce_api_' . strtolower(static::WOOCOMMERCE_API_RETURN_ADD_PAYMENT_CHECKOUT), [$this, 'process_inscription_return_checkout']);
        add_filter('woocommerce_payment_methods_list_item', [$this, 'methods_list_item_oneclick'], null, 2);
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'process_admin_options']);
    }

    public function is_valid_for_use() {
        return in_array(
            get_woocommerce_currency(),
            apply_filters(
                'woocommerce_transbank_webpay_oneclick_supported_currencies',
                ['CLP']
            ),
            true
        );
    }

    public function admin_options() {
        if ( $this->is_valid_for_use() ) {
            parent::admin_options();
        } else {
            ?>
            <div class="inline error">
                <p>
                    <strong><?php esc_html_e( 'Gateway disabled', 'woocommerce' ); ?></strong>: <?php esc_html_e( 'Oneclick no soporta la moneda configurada en tu tienda. Solo soporta CLP', 'woocommerce' ); ?>
                </p>
            </div>
            <?php
        }
    }

    public function is_available() {
        if (!$this->is_valid_for_use()) {
            return false;
        }
        return parent::is_available();
    }

    public function form()

    {
        ?>
        <fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
            <input type="hidden" name="transbank_oneclick_add_new_card" value="true">
            Selecciona esta opción para agregar una nueva tarjeta a través de Webpay Oneclick <br><br>
        </fieldset>
        <?php
    }

    public static function scheduled_subscription_payment($amount_to_charge, $order)
    {
        //TODO: Add this process
        //file_put_contents(__DIR__ . '/test.txt',  print_r([$amount_to_charge, $order], true));
    }

    public function methods_list_item_oneclick($item, $payment_token)
    {
        if ('oneclick' !== strtolower($payment_token->get_type())) {
            return $item;
        }
        $item['method']['last4'] = $payment_token->get_last4();
        $item['method']['brand'] = $payment_token->get_card_type();

        return $item;
    }

    /**
     * Procesar pago y retornar resultado.
     **/
    public function process_payment($order_id)
    {
        global $woocommerce;
        if (isset($_POST["wc-{$this->id}-new-payment-method"])) {
            $userInfo = wp_get_current_user();
            $returnUrl = add_query_arg('wc-api', static::WOOCOMMERCE_API_RETURN_ADD_PAYMENT_CHECKOUT, home_url('/'));
            $response = $this->oneclickInscription->start($this->generateOneclickUsername($userInfo), $userInfo->user_email,
                $returnUrl);

            return [
                'result' => 'success',
                'redirect' => $response->getRedirectUrl()
            ];
        }

        if (isset($_POST["wc-{$this->id}-payment-token"]) && 'new' !== $_POST["wc-{$this->id}-payment-token"]) {
            $token_id = wc_clean($_POST["wc-{$this->id}-payment-token"]);

            /** @var WC_Payment_Token_Oneclick $token */
            $token = \WC_Payment_Tokens::get($token_id);

            $order = new WC_Order($order_id);
            $amount = (int) number_format($order->get_total(), 0, ',', '');

            $childCommerceCode = $this->get_option('environment') === Options::ENVIRONMENT_PRODUCTION ?
                $this->get_option('child_commerce_code') :
                Oneclick::DEFAULT_CHILD_COMMERCE_CODE_1;

            $childBuyOrder = 'S' . $order->get_id();
            $details = [
                [
                    "commerce_code" => $childCommerceCode,
                    "buy_order" => $childBuyOrder, // Tu propio buyOrder
                    "amount" => $amount,
                    "installments_number" => 1
                ]
            ];

            $response = $this->oneclickTransaction->authorize($token->get_username(), $token->get_token(), $order->get_id(),
                $details);

            $status = TransbankWebpayOrders::STATUS_FAILED;
            if ($response->isApproved()) {
                $status = TransbankWebpayOrders::STATUS_APPROVED;
                $order->payment_complete();
                $woocommerce->cart->empty_cart();
                $this->add_order_notes($order, $response);
            } else {
                // Todo: Mejorar información sobre este errror. Probablemente es por CONSTRAINT_VIOLATED o algo así.
            }

            TransbankWebpayOrders::createTransaction([
                'order_id'   => $order->get_id(),
                'buy_order'  => $order->get_id(),
                'child_buy_order'  => $childBuyOrder,
                'commerce_code'  => $this->oneclickInscription->getOptions()->getCommerceCode(),
                'child_commerce_code'  => $childCommerceCode,
                'amount'     => $amount,
                'environment' => $this->oneclickInscription->getOptions()->getIntegrationType(),
                'product'     => TransbankWebpayOrders::PRODUCT_WEBPAY_ONECLICK,
                'status'     => $status,
                'transbank_status' => $response->getDetails()[0]->getStatus() ?? null,
                'transbank_response' => json_encode($response)
            ]);

            return [
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            ];
        }
        wc_add_notice(__('Error interno: no se pudo procesar el pago', 'transbank'), 'error');

        return;
    }

    public function add_payment_method()
    {
        $userInfo = wp_get_current_user();
        $returnUrl = add_query_arg('wc-api', static::WOOCOMMERCE_API_RETURN_ADD_PAYMENT, home_url('/'));
        $response = $this->oneclickInscription->start($this->generateOneclickUsername($userInfo), $userInfo->user_email, $returnUrl);
        $redirectUrl = $response->getRedirectUrl();

        return wp_redirect($redirectUrl);
    }

    public function process_inscription_return_checkout()
    {
        $this->process_inscription_return('checkout');
    }

    public function process_inscription_return($from = '')
    {
        $this->logger->logInfo('[ONECLICK] Process inscription return: GET ' . print_r($_GET, true) . ' | POST: ' . print_r($_POST, true));
        $tbkToken = $_GET['TBK_TOKEN'] ?? $_POST['TBK_TOKEN'] ?? null;
        if (!$tbkToken) {
            $this->finishWithError('No se puede acceder a esta página directamente');
        }
        $tbkOrdenCompra = $_GET['TBK_ORDEN_COMPRA'] ?? $_POST['TBK_ORDEN_COMPRA'] ?? null;

        if ($tbkOrdenCompra) {
            // TODO: Mejorar este caso marcando la inscripción como abortada
            wc_add_notice('Has anulado la inscripción', 'warning');
            $this->logger->logError('La inscripción fue anulada por el usuario o hubo un error en el formulario de pago');
            $this->redirectUser($from);
        }
        try {
            $response = $this->oneclickInscription->finish($tbkToken);
        } catch (\Exception $e) {
            $this->logger->logError('Ocurrió un error al ejecutar la inscripción: ' . $e->getMessage());
            wc_add_notice('Ocurrió un error en la inscripción de la tarjeta: '. $e->getMessage(), 'error');
            $this->redirectUser($from);
        }

        // Todo: guardar la información del usuario al momento de crear la inscripción y luego obtenerla en base al token,
        // por si se pierde la sesión
        $userInfo = wp_get_current_user();
        if (!$userInfo) {
            $this->finishWithError('You were logged out');
        }

        $this->logger->logInfo('[ONECLICK] Resultado obtenido correctamente: ' . print_r($response, true));
        if ($response->isApproved()) {
            wc_add_notice('La tarjeta ha sido inscrita satisfactoriamente','success');
            $this->logger->logInfo('[ONECLICK] Inscripción aprobada');
            $token = new WC_Payment_Token_Oneclick();
            $token->set_token($response->getTbkUser()); // Token comes from payment processor
            $token->set_gateway_id($this->id);
            $token->set_last4(substr($response->getCardNumber(), -4));
            $token->set_email($userInfo->user_email);
            $token->set_username($this->generateOneclickUsername($userInfo));
            $token->set_card_type($response->getCardType()); // TODO: cambiar esto
            $token->set_user_id(get_current_user_id());
            $token->set_environment($this->oneclickInscription->getOptions()->getIntegrationType());
            // Save the new token to the database
            $token->save();

            // Set this token as the users new default token
            WC_Payment_Tokens::set_users_default(get_current_user_id(), $token->get_id());

        } else {
            //Todo: In case that the inscription fails, we need to redirect the user somewhere.

            $this->logger->logInfo('[ONECLICK] Inscripción fallida');
        }

        $redirectUrl = null;

        $this->redirectUser($from);
    }

    /**
     * Inicializar campos de formulario.
     **/
    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Activar/Desactivar', 'transbank_webpay_plus_rest'),
                'type' => 'checkbox',
                'default' => 'yes',
            ],
            'environment' => [
                'title' => __('Ambiente', 'transbank_webpay_plus_rest'),
                'type' => 'select',
                'description' => 'Define si el plugin operará en el ambiente de pruebas (integración) o en el
                    ambiente real (producción). Si defines el ambiente como "Integración" <strong>no</strong> se usarán el código de
                    comercio y llave secreta que tengas configurado abajo, ya que se usará el código de comercio especial del ambiente de pruebas.',
                'options' => [
                    Options::ENVIRONMENT_INTEGRATION => __('Integración', 'transbank_webpay_plus_rest'),
                    Options::ENVIRONMENT_PRODUCTION => __('Producción', 'transbank_webpay_plus_rest'),
                ],
                'default' => Options::ENVIRONMENT_INTEGRATION,
            ],
            'commerce_code' => [
                'title' => __('Código de Comercio Mall Producción', 'transbank_webpay_plus_rest'),
                'placeholder' => 'Ej: 597012345678',
                'description' => 'Indica tu código de comercio para el ambiente de producción. Este se te entregará al completar el proceso de afiliación comercial. Siempre comienza con 5970 y debe tener 12 dígitos. Si el tuyo tiene 8, antepone 5970. ',
                'type' => 'text',
                'default' => '',
            ],
            'child_commerce_code' => [
                'title' => __('Código de Comercio Tienda Producción', 'transbank_webpay_plus_rest'),
                'placeholder' => 'Ej: 597012345678',
                'description' => 'Indica tu código de comercio para el ambiente de producción. Este se te entregará al completar el proceso de afiliación comercial. Siempre comienza con 5970 y debe tener 12 dígitos. Si el tuyo tiene 8, antepone 5970. ',
                'type' => 'text',
                'default' => '',
            ],
            'api_key' => [
                'title' => __('API Key (llave secreta) producción', 'transbank_webpay_plus_rest'),
                'type' => 'text',
                'placeholder' => 'Ej: XXXXXXXXXXXXXXXXXXXXXXXXXXXX',
                'description' => 'Esta llave secreta te la entregará Transbank luego de que completes el proceso de validación (link más abajo). No la compartas con nadie una vez que la tengas. ',
                'default' => '',
            ],
            'max_amount' => [
                'title' => __('Monto máximo de transacción permitido', 'transbank_webpay_plus_rest'),
                'type' => 'number',
                'options' => ['step' => 100],
                'default' => '100000',
                'description' => 'Define el monto máximo que un cliente puede pagar con Oneclick.
                Si un cliente va a realizar una compra superior a este monto, Oneclick no aparecerá como opción de
                pago en el Checkout. Dejar en 0 si no se desea tener un límite (no recomendado). Recuerda que Oneclick,
                al no tener una autorización bancaria para cada pago, es un producto donde el riesgo puede pasar más
                fácilmente hacia el comercio.'
            ],
        ];
    }
    /**
     * @param \WP_User $userInfo
     * @return string
     */
    public function generateOneclickUsername(\WP_User $userInfo): string
    {
        return 'WP' . $userInfo->ID;
    }
    protected function add_order_notes(WC_Order $wooCommerceOrder, $response)
    {
        /** @var Oneclick\Responses\TransactionDetail $firstDetail */
        $message = 'Oneclick: Transacción Aprobada';
        $firstDetail = $response->getDetails()[0];
        $amountFormatted = number_format($firstDetail->getAmount(), 0, ',', '.');
        $sharesAmount = $firstDetail->getInstallmentsAmount() ?? '-';
        $transactionDetails = "
            <div class='transbank_response_note'>
                <p><h3>{$message}</h3></p>

                <strong>Estado: </strong>{$firstDetail->getStatus()} <br />
                <strong>Orden de compra principal: </strong>{$response->getBuyOrder()} <br />
                <strong>Orden de compra: </strong>{$firstDetail->getBuyOrder()} <br />
                <strong>Código de autorización: </strong>{$firstDetail->getAuthorizationCode()} <br />
                <strong>Últimos dígitos tarjeta: </strong>{$response->getCardNumber()} <br />
                <strong>Monto: </strong>$ {$amountFormatted} <br />
                <strong>Código de respuesta: </strong>{$firstDetail->getResponseCode()} <br />
                <strong>Tipo de pago: </strong>{$firstDetail->getPaymentTypeCode()} <br />
                <strong>Número de cuotas: </strong>{$firstDetail->getInstallmentsNumber()} <br />
                <strong>Monto de cada cuota: </strong>{$sharesAmount} <br />
                <strong>Fecha:</strong> {$response->getTransactionDate()} <br />
                <strong>Fecha contable:</strong> {$response->getAccountingDate()} <br />
            </div>
        ";
        $wooCommerceOrder->add_order_note($transactionDetails);
        $wooCommerceOrder->add_meta_data('transbank_response', json_encode($response));
    }
    /**
     * @param string $message
     */
    public function finishWithError(string $message)
    {
        $this->logger->logError('[ONECLICK] [Error]: ' . $message);
    }
    /**
     * @param $from
     */
    public function redirectUser($from): void
    {
        if ($from === 'checkout') {
            $checkout_page_id = wc_get_page_id('checkout');
            $redirectUrl = $checkout_page_id ? get_permalink($checkout_page_id) : null;
        }
        if ($from === '') {
            $redirectUrl = get_permalink(get_option('woocommerce_myaccount_page_id')) . '/' . get_option('woocommerce_myaccount_payment_methods_endpoint',
                    'payment-methods');
        }
        if ($redirectUrl) {
            wp_redirect($redirectUrl);
        }

        die();
    }
}
