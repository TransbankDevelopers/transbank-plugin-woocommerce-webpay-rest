<?php
namespace Transbank\WooCommerce\WebpayRest\PaymentGateways;

use Transbank\Webpay\Exceptions\WebpayRequestException;
use Transbank\Webpay\Oneclick;
use WC_Order;
use WC_Payment_Token_Oneclick;
use WC_Payment_Gateway_CC;
use WC_Payment_Tokens;

class WC_Gateway_Transbank_Oneclick_Mall_REST extends WC_Payment_Gateway_CC
{
    const WOOCOMMERCE_API_SLUG = 'WC_Gateway_Transbank_Oneclick';
    /**
     * @var Oneclick\MallInscription
     */
    protected $oneclickInscription;
    /**
     * @var Oneclick\MallTransaction
     */
    protected $oneclickTransaction;

    /**
     * WC_Gateway_Transbank_Oneclick_Mall_REST constructor.
     */
    public function __construct()
    {
        $this->supports = ['tokenization'];
        $this->title = 'Webpay Oneclick';
        $this->method_title = 'Webpay Oneclick';
        $this->description = 'Permite el pago de productos y/o servicios, con tarjetas de crédito, débito y prepago a través de Webpay Plus';
        $this->method_description = 'Permite el pago de productos y/o servicios, con tarjetas de crédito, débito y prepago a través de Webpay Plus';
        $this->icon = plugin_dir_url(dirname(dirname(__FILE__))) . 'libwebpay/images/webpay.png';
        $this->id = 'transbank_oneclick_mall_rest';
        $this->init_form_fields();
        $this->init_settings();

        $this->oneclickInscription = new Oneclick\MallInscription();
        $this->oneclickTransaction = new Oneclick\MallTransaction();

        add_action('woocommerce_api_' . strtolower(static::WOOCOMMERCE_API_SLUG), [$this, 'check_ipn_response']);
        add_filter('woocommerce_payment_methods_list_item', [$this, 'methods_list_item_oneclick'], null, 2);
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
        if ($_POST["wc-{$this->id}-new-payment-method"]) {
            $userInfo = wp_get_current_user();
            $returnUrl = add_query_arg('wc-api', static::WOOCOMMERCE_API_SLUG, home_url('/'));
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
            $amount = (int)number_format($order->get_total(), 0, ',', '');

            $details = [
                [
                    "commerce_code" => Oneclick::DEFAULT_CHILD_COMMERCE_CODE_1,
                    "buy_order" => 'S' . $order->ID, // Tu propio buyOrder
                    "amount" => $amount,
                    "installments_number" => 1
                ]
            ];

            $response = $this->oneclickTransaction->authorize($token->get_username(), $token->get_token(), $order->ID,
                $details);

            if ($response->isApproved()) {
                $order->payment_complete();
                $woocommerce->cart->empty_cart();
                $this->add_order_notes($order, $response);
                wc_add_notice(__('Transacción aprobada', 'transbank'), 'success');
            }


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
        $returnUrl = add_query_arg('wc-api', static::WOOCOMMERCE_API_SLUG, home_url('/'));
        $response = $this->oneclickInscription->start($this->generateOneclickUsername($userInfo), $userInfo->user_email, $returnUrl);
        $redirectUrl = $response->getRedirectUrl();

        return wp_redirect($redirectUrl);
    }

    public function check_ipn_response()
    {

        $tbkToken = $_GET['TBK_TOKEN'] ?? $_POST['TBK_TOKEN'] ?? null;
        if (!$tbkToken) {
            die('No se puede acceder a esta página directamente');
        }
        $tbkOrdenCompra = $_GET['TBK_ORDEN_COMPRA'] ?? $_POST['TBK_ORDEN_COMPRA'] ?? null;
        if ($tbkOrdenCompra) {
            // TODO: Mejorar este caso
            die('La transacción fue anulada por el usuario o hubo un error en el formulario de pago');
        }
        try {
            $response = $this->oneclickInscription->finish($tbkToken);
        } catch (WebpayRequestException $e) {
            die('Ocurrió un error al ejecutar la inscripción: ' . $e->getMessage());
        }

        // Todo: guardar la información del usuario al momento de crear la inscripción y luego obtenerla en base al token,
        // por si se pierde la sesión
        $userInfo = wp_get_current_user();
        if (!$userInfo) {
            die('You were logged out');
        }

        if ($response->isApproved()) {

            $token = new WC_Payment_Token_Oneclick();
            $token->set_token($response->getTbkUser()); // Token comes from payment processor
            $token->set_gateway_id($this->id);
            $token->set_last4(substr($response->getCardNumber(), -4));
            $token->set_email($userInfo->user_email);
            $token->set_username($this->generateOneclickUsername($userInfo));
            $token->set_card_type($response->getCardType()); // TODO: cambiar esto
            $token->set_user_id(get_current_user_id());
// Save the new token to the database
            $token->save();

// Set this token as the users new default token
            WC_Payment_Tokens::set_users_default(get_current_user_id(), $token->get_id());

        } else {
            die('Inscripción fallida');
        }

        die();
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
            'webpay_rest_environment' => [
                'title' => __('Ambiente', 'transbank_webpay_plus_rest'),
                'type' => 'select',
                'description' => 'Define si el plugin operará en el ambiente de pruebas (integración) o en el
                    ambiente real (producción). Si defines el ambiente como "Integración" <strong>no</strong> se usarán el código de
                    comercio y llave secreta que tengas configurado abajo, ya que se usará el código de comercio especial del ambiente de pruebas.',
                'options' => [
                    'TEST' => __('Integración', 'transbank_webpay_plus_rest'),
                    'LIVE' => __('Producción', 'transbank_webpay_plus_rest'),
                ],
                'default' => 'TEST',
            ],
            'webpay_rest_commerce_code' => [
                'title' => __('Código de Comercio Mall Producción', 'transbank_webpay_plus_rest'),
                'placeholder' => 'Ej: 597012345678',
                'description' => 'Indica tu código de comercio para el ambiente de producción. Este se te entregará al completar el proceso de afiliación comercial. Siempre comienza con 5970 y debe tener 12 dígitos. Si el tuyo tiene 8, antepone 5970. ',
                'type' => 'text',
                'default' => '',
            ],
            'webpay_rest_child_commerce_code' => [
                'title' => __('Código de Comercio Tienda Producción', 'transbank_webpay_plus_rest'),
                'placeholder' => 'Ej: 597012345678',
                'description' => 'Indica tu código de comercio para el ambiente de producción. Este se te entregará al completar el proceso de afiliación comercial. Siempre comienza con 5970 y debe tener 12 dígitos. Si el tuyo tiene 8, antepone 5970. ',
                'type' => 'text',
                'default' => '',
            ],
            'webpay_rest_api_key' => [
                'title' => __('API Key (llave secreta) producción', 'transbank_webpay_plus_rest'),
                'type' => 'text',
                'placeholder' => 'Ej: XXXXXXXXXXXXXXXXXXXXXXXXXXXX',
                'description' => 'Esta llave privada te la entregará Transbank luego de que completes el proceso de validación (link más abajo). No la compartas con nadie una vez que la tengas. ',
                'default' => '',
            ],
        ];
    }
    /**
     * @param \WP_User $userInfo
     * @return string
     */
    public function generateOneclickUsername(\WP_User $userInfo): string
    {
        return 'WP' . $userInfo->ID . '-' . rand(1000, 9999);
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
}
