<?php

namespace Transbank\WooCommerce\WebpayRest\PaymentGateways;

use Transbank\Webpay\Oneclick;
use Transbank\Webpay\Options;
use Transbank\WooCommerce\WebpayRest\Controllers\OneclickInscriptionResponseController;
use Transbank\WooCommerce\WebpayRest\Helpers\ErrorHelper;
use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;
use Transbank\WooCommerce\WebpayRest\Models\Inscription;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\Telemetry\PluginVersion;
use Transbank\WooCommerce\WebpayRest\Helpers\WordpressPluginVersion;
use WC_Order;
use WC_Payment_Gateway_CC;
use WC_Payment_Token_Oneclick;
use WC_Payment_Tokens;

/**
 * Class WC_Gateway_Transbank_Oneclick_Mall_REST.
 */
class WC_Gateway_Transbank_Oneclick_Mall_REST extends WC_Payment_Gateway_CC
{
    use TransbankRESTPaymentGateway;

    const WOOCOMMERCE_API_RETURN_ADD_PAYMENT = 'wc_gateway_transbank_oneclick_return_payments';
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

        $this->id = 'transbank_oneclick_mall_rest';
        $this->title = 'Webpay Oneclick';
        $this->method_title = 'Webpay Oneclick';
        $this->description = 'Inscribe tu tarjeta de crédito, débito o prepago y luego paga con un solo click a través de Webpay Oneclick';
        $this->method_description = 'Inscribe tu tarjeta de crédito, débito o prepago y luego paga con un solo click a través de Webpay Oneclick';
        $this->icon = plugin_dir_url(dirname(dirname(__FILE__))).'images/oneclick.svg';

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
                $this->get_option('api_key')
            );

            $this->oneclickTransaction->configureForProduction(
                $this->get_option('commerce_code'),
                $this->get_option('api_key')
            );
        }

        add_action(
            'woocommerce_scheduled_subscription_payment_'.$this->id,
            [$this, 'scheduled_subscription_payment'],
            10,
            3
        );
        add_action('woocommerce_api_'.strtolower(static::WOOCOMMERCE_API_RETURN_ADD_PAYMENT), [
            new OneclickInscriptionResponseController($this->oneclickInscription, $this->id, $this->logger),
            'response',
        ]);

        add_filter('woocommerce_payment_methods_list_item', [$this, 'methods_list_item_oneclick'], null, 2);
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'registerPluginVersion']);

    }

    public function registerPluginVersion()
    {

        $commerceCode = $this->get_option('commerce_code');

        $pluginVersion = $this->getPluginVersion();

        (new PluginVersion())->registerVersion(
            $commerceCode,
            $pluginVersion,
            wc()->version,
            PluginVersion::ECOMMERCE_WOOCOMMERCE,
            $this->get_option('environment'),
            'oneclick'
        );
    }

    /**
     * @return mixed
     */
    public function getPluginVersion()
    {
        return (new WordpressPluginVersion())->get();
    }

    public function payment_fields()
    {
        $description = $this->get_description();
        if ($description) {
            echo wpautop(wptexturize($description)); // @codingStandardsIgnoreLine.
        }
        parent::payment_fields();
    }

    public function is_valid_for_use()
    {
        return in_array(
            get_woocommerce_currency(),
            apply_filters('woocommerce_transbank_webpay_oneclick_supported_currencies', ['CLP']),
            true
        );
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = new WC_Order($order_id);
        $transaction = Transaction::getApprovedByOrderId($order_id);

        if (!$transaction) {
            $message = 'Se intentó anular transacción, pero no se encontró en la base de datos de transacciones de webpay plus. ';
            do_action('transbank_oneclick_refund_failed', $order, $transaction);
            $this->failedRefund($order, $message);
        }
        $response = [];

        try {
            $response = $this->oneclickTransaction->refund($transaction->buy_order, $transaction->child_commerce_code, $transaction->child_buy_order, round($amount));
            $jsonResponse = json_encode($response, JSON_PRETTY_PRINT);
            do_action('transbank_oneclick_refund_finished', $order, $transaction, $jsonResponse);
        } catch (\Exception $e) {
            $message = 'Error al anular: '.$e->getMessage();
            do_action('transbank_oneclick_refund_failed', $order, $transaction);
            $this->failedRefund($order, $message);
        }

        if ($response->getType() === 'REVERSED' || ($response->getType() === 'NULLIFIED' && (int) $response->getResponseCode() === 0)) {
            $this->addRefundOrderNote($response, $order, $amount, $jsonResponse);
            do_action('transbank_oneclick_refund_approved', $order, $transaction);

            return true;
        } else {
            $message = 'Anulación a través de Webpay FALLIDA. '."\n\n".$jsonResponse;
            do_action('transbank_oneclick_refund_failed', $order, $transaction);
            $this->failedRefund($order, $message);
        }

        return false;
    }

    public function admin_options()
    {
        if ($this->is_valid_for_use()) {
            $tab = 'options_oneclick';
            $environment = $this->get_option('environment');
            $showedWelcome = get_site_option('transbank_webpay_oneclick_rest_showed_welcome_message');
            update_site_option('transbank_webpay_oneclick_rest_showed_welcome_message', true);
            include __DIR__.'/../../views/admin/options-tabs.php';
        } else {
            ?>
            <div class="inline error">
                <p>
                    <strong><?php esc_html_e(
                'Gateway disabled',
                'woocommerce'
            ); ?></strong>: <?php esc_html_e(
                'Oneclick no soporta la moneda configurada en tu tienda. Solo soporta CLP',
                'transbank_wc_plugin'
            ); ?>
                </p>
            </div>
            <?php
        }
    }

    public function getStatus($buyOrder)
    {
        return $this->oneclickTransaction->status($buyOrder);
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
    }

    /**
     * @throws Oneclick\Exceptions\MallTransactionAuthorizeException
     */
    public function scheduled_subscription_payment($amount_to_charge, WC_Order $renewalOrder)
    {
        (new LogHandler())->logInfo('New scheduled_subscription_payment for Order #'.$renewalOrder->get_id());
        $customerId = $renewalOrder->get_customer_id();
        if (!$customerId) {
            (new LogHandler())->logError('There is no costumer id on the renewal order');

            throw new Exception('There is no costumer id on the renewal order');
        }

        /** @var WC_Payment_Token_Oneclick $paymentToken */
        $paymentToken = WC_Payment_Tokens::get_customer_default_token($customerId);
        $this->authorizeTransaction($renewalOrder, $paymentToken, $amount_to_charge);
        $this->setAfterPaymentOrderStatus($renewalOrder);
    }

    public static function subscription_payment_method_updated()
    {
        // Todo: check if we need something here.
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
     **
     *
     * @throws Oneclick\Exceptions\MallTransactionAuthorizeException
     */
    public function process_payment($order_id)
    {
        $order = new WC_Order($order_id);

        if (!$order->needs_payment() && !wcs_is_subscription($order_id)) {
            $this->logger->logError('This order was already paid or does not need payment');
            wc_add_notice(__(
                'Esta transacción puede ya estar pagada o encontrarse en un estado que no permite un nuevo pago. ',
                'transbank_wc_plugin'
            ), 'error');

            return [
                'result' => 'error',
            ];
        }

        $paymentMethodOption = $_POST["wc-{$this->id}-payment-token"] ?? null;
        $addNewCard = 'new' === $paymentMethodOption || $paymentMethodOption === null;
        $payWithSavedToken = $paymentMethodOption !== null && !$addNewCard;

        if (!get_current_user_id()) {
            $order->add_order_note('El usuario intentó pagar con oneclick pero no tiene (y no creó durante el checkout) cuenta de usuario');
            $this->logger->logInfo('Checkout: The user should have an account to add a new card. ');
            wc_add_notice(__(
                'Webpay Oneclick: Debes crear o tener una cuenta en el sitio para poder inscribir tu tarjeta y usar este método de pago.',
                'transbank_wc_plugin'
            ), 'error');

            return [
                'result' => 'error',
            ];
        }

        if ($addNewCard) {
            $this->logger->logInfo('[Oneclick] Checkout: start inscription');

            try {
                $response = $this->startInscription($order_id);
            } catch (\Throwable $e) {
                $errorMessage = ErrorHelper::getErrorMessageBasedOnTransbankSdkException($e);

                return wc_add_notice($errorMessage, 'error');
            }
            $this->logger->logInfo('[O neclick] Checkout: inscription response: ');
            $this->logger->logInfo(print_r($response, true));
            $order->add_order_note('El usuario inició inscripción de nueva tarjeta. Redirigiendo a formulario OneClick...');

            do_action('transbank_oneclick_adding_card_from_order', $order);

            return [
                'result'   => 'success',
                'redirect' => $response->getRedirectUrl(),
            ];
        }

        if ($payWithSavedToken) {
            try {
                return $this->authorizeTransaction($order);
            } catch (\Throwable $e) {
                $errorMessage = ErrorHelper::getErrorMessageBasedOnTransbankSdkException($e);

                return wc_add_notice($errorMessage, 'error');
            }
        }
        wc_add_notice(__('Error interno: no se pudo procesar el pago', 'transbank_wc_plugin'), 'error');
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws Oneclick\Exceptions\InscriptionStartException
     */
    public function add_payment_method()
    {
        $response = $this->startInscription(null, 'my_account');
        $redirectUrl = $response->getRedirectUrl();

        return wp_redirect($redirectUrl);
    }

    /**
     * Outputs a checkbox for saving a new payment method to the database.
     *
     * @since 2.6.0
     */
    public function save_payment_method_checkbox()
    {
        echo '<p class="form-row woocommerce-SavedPaymentMethods-saveNew"><strong>Esta tarjeta se guardará en tu cuenta para que puedas volver a usarla.</strong></p>';
    }

    /**
     * Inicializar campos de formulario.
     **/
    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => __('Activar/Desactivar', 'transbank_wc_plugin'),
                'type'    => 'checkbox',
                'default' => 'no',
            ],
            'environment' => [
                'title'       => __('Ambiente', 'transbank_wc_plugin'),
                'type'        => 'select',
                'desc_tip'    => 'Define si el plugin operará en el ambiente de pruebas (integración) o en el
                    ambiente real (producción). <br /><br />Si defines el ambiente como "Integración" <strong>no</strong> se usarán el código de
                    comercio y llave secreta que tengas configurado abajo, ya que se usará el código de comercio especial del ambiente de pruebas.',
                'options' => [
                    Options::ENVIRONMENT_INTEGRATION => __('Integración', 'transbank_wc_plugin'),
                    Options::ENVIRONMENT_PRODUCTION  => __('Producción', 'transbank_wc_plugin'),
                ],
                'default' => Options::ENVIRONMENT_INTEGRATION,
            ],
            'commerce_code' => [
                'title'       => __('Código de Comercio Mall Producción', 'transbank_wc_plugin'),
                'placeholder' => 'Ej: 597012345678',
                'desc_tip'    => 'Ingresa tu código de comercio Mall para el ambiente de producción. <br /><br />Este se te entregará al completar el proceso de afiliación comercial. <br /><br />Siempre comienza con 5970 y debe tener 12 dígitos. Si el tuyo tiene 8, antepone 5970. ',
                'type'        => 'text',
                'default'     => '',
            ],
            'child_commerce_code' => [
                'title'       => __('Código de Comercio Tienda Producción', 'transbank_wc_plugin'),
                'placeholder' => 'Ej: 597012345678',
                'desc_tip'    => 'Indica tu código de comercio Tienda para el ambiente de producción. <br /><br />Este se te entregará al completar el proceso de afiliación comercial. <br /><br />Siempre comienza con 5970 y debe tener 12 dígitos. Si el tuyo tiene 8, antepone 5970. ',
                'type'        => 'text',
                'default'     => '',
            ],
            'api_key' => [
                'title'       => __('API Key (llave secreta) producción', 'transbank_wc_plugin'),
                'type'        => 'text',
                'placeholder' => 'Ej: XXXXXXXXXXXXXXXXXXXXXXXXXXXX',
                'desc_tip'    => 'Esta llave secreta te la entregará Transbank luego de que completes el proceso de validación (link más abajo). <br /><br />No la compartas con nadie una vez que la tengas. ',
                'default'     => '',
            ],
            'max_amount' => [
                'title'       => __('Monto máximo de transacción permitido', 'transbank_wc_plugin'),
                'type'        => 'number',
                'options'     => ['step' => 100],
                'default'     => '0',
                'desc_tip'    => 'Define el monto máximo que un cliente puede pagar con Oneclick.<br /><br />
                Si un cliente va a realizar una compra superior a este monto, Oneclick no aparecerá como opción de
                pago en el proceso de checkout. Dejar en 0 si no se desea tener un límite (no recomendado). <br /><br />Recuerda que en
                Oneclick, al no contar con autentificación bancaria, es tu comercio el que asume el riesgo en caso d
                e fraude o contracargo. <br /><br />Independiente del monto que definas en esta configuración, en tu
                contrato de Oneclick, existe un límite de cantidad de transacciones diarias, un monto máximo por
                transacción y monto acumulado diario. Si un cliente supera ese límite, su transacción será rechazada. ',
            ],
            'oneclick_after_payment_order_status' => [
                'title'       => __('Order Status', 'transbank_wc_plugin'),
                'type'        => 'select',
                'desc_tip'    => 'Define el estado de la orden luego del pago exitoso.',
                'options' => [
                    '' => 'Default',
                    'processing' => 'Processing',
                    'completed'  => 'Completed',
                ],
                'default' => '',
            ],
        ];
    }

    /**
     * @param \WP_User $userInfo
     *
     * @return string
     */
    public function generateOneclickUsername(\WP_User $userInfo): string
    {
        return 'WP:'.$userInfo->ID.':'.uniqid();
    }

    protected function add_order_notes(WC_Order $wooCommerceOrder, $response, $message)
    {
        /** @var Oneclick\Responses\TransactionDetail $firstDetail */
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
     * @param int|null $order_id
     * @param string   $from
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws Oneclick\Exceptions\InscriptionStartException
     *
     * @return Oneclick\Responses\InscriptionStartResponse
     */
    public function startInscription(
        int $order_id = null,
        string $from = 'checkout'
    ): Oneclick\Responses\InscriptionStartResponse {
        // The user selected Oneclick, Pay with new card and choosed to save it in their account.
        $userInfo = wp_get_current_user();
        $returnUrl = add_query_arg('wc-api', static::WOOCOMMERCE_API_RETURN_ADD_PAYMENT, home_url('/'));
        $username = $this->generateOneclickUsername($userInfo);
        $email = $userInfo->user_email; // Todo: check if we had to generate a random email as well
        $response = $this->oneclickInscription->start($username, $email, $returnUrl);

        $insert = Inscription::create([
            'token'                 => $response->getToken(),
            'username'              => $username,
            'order_id'              => $order_id,
            'user_id'               => $userInfo->ID,
            'pay_after_inscription' => false,
            'email'                 => $email,
            'from'                  => $from,
            'status'                => Inscription::STATUS_INITIALIZED,
            'environment'           => $this->oneclickInscription->getOptions()->getIntegrationType(),
            'commerce_code'         => $this->oneclickInscription->getOptions()->getCommerceCode(),
        ]);

        if( !$insert ) {
            $table = Inscription::getTableName();
            $wpdb->show_errors();
            $errorMessage = "La inscripción no se pudo registrar en la tabla: '{$table}', query: {$wpdb->last_query}, error: {$wpdb->last_error}";
            $this->logger->logInfo($errorMessage);
            throw new Exception($errorMessage);
        }

        return $response;
    }

    /**
     * @param WC_Order $order
     *
     * @throws Oneclick\Exceptions\MallTransactionAuthorizeException
     *
     * @return array
     */
    public function authorizeTransaction(
        WC_Order $order,
        WC_Payment_Token_Oneclick $paymentToken = null,
        $amount = null
    ): array {
        global $wpdb;

        if ($paymentToken) {
            $token = $paymentToken;
        } else {
            $token_id = wc_clean($_POST["wc-{$this->id}-payment-token"]);
            /** @var WC_Payment_Token_Oneclick $token */
            $token = \WC_Payment_Tokens::get($token_id);
        }

        $this->logger->logInfo('[Oneclick] Checkout: paying with token ID #'.$token->get_id());

        if ($amount == null) {
            $amount = (int) number_format($order->get_total(), 0, ',', '');
        }

        $childCommerceCode = $this->get_option('environment') === Options::ENVIRONMENT_PRODUCTION ? $this->get_option('child_commerce_code') : Oneclick::DEFAULT_CHILD_COMMERCE_CODE_1;

        $childBuyOrder = 'C'.$order->get_id();
        $details = [
            [
                'commerce_code'       => $childCommerceCode,
                'buy_order'           => $childBuyOrder, // Tu propio buyOrder
                'amount'              => $amount,
                'installments_number' => 1,
            ],
        ];

        $response = $this->oneclickTransaction->authorize(
            $token->get_username(),
            $token->get_token(),
            $order->get_id(),
            $details
        );

        $this->logger->logInfo('[Oneclick] Checkout: paying response');
        $this->logger->logInfo(print_r($response, true));
        $status = $response->isApproved() ? Transaction::STATUS_APPROVED : Transaction::STATUS_FAILED;

        if ($response->isApproved()) {
            $order->add_payment_token($token);
            $this->setAfterPaymentOrderStatus($order);
            if (wc()->cart) {
                wc()->cart->empty_cart();
            }
            $this->add_order_notes($order, $response, 'Oneclick: Transacción Aprobada');
        } else {
            $this->logger->logInfo('[Oneclick] Checkout: authorization rejected');
            $errorCode = $response->getDetails()[0]->getResponseCode() ?? null;
            $status = $response->getDetails()[0]->getStatus() ?? null;
            $order->update_status('failed');
            $this->add_order_notes($order, $response, 'Oneclick: Transacción Rechazada');

            $orderNoteMessage = 'Transacción rechazada';
            if ($status === 'CONSTRAINTS_VIOLATED') {
                $message = 'La transacción ha sido rechazada porque se superó el monto máximo por transacción, el monto máximo diario o el número de transacciones diarias configuradas por el comercio para cada usuario';
                $orderNoteMessage = 'CONSTRAINTS_VIOLATED: '.$message;
                wc_add_notice($message, 'error');
            } else {
                wc_add_notice('La transacción ha sido rechazada (Código de error: '.$errorCode.')', 'error');
            }

            if ($orderNoteMessage) {
                $order->add_order_note($orderNoteMessage);
            }
        }

        $insert = Transaction::createTransaction([
            'order_id'            => $order->get_id(),
            'buy_order'           => $order->get_id(),
            'child_buy_order'     => $childBuyOrder,
            'commerce_code'       => $this->oneclickInscription->getOptions()->getCommerceCode(),
            'child_commerce_code' => $childCommerceCode,
            'amount'              => $amount,
            'environment'         => $this->oneclickInscription->getOptions()->getIntegrationType(),
            'product'             => Transaction::PRODUCT_WEBPAY_ONECLICK,
            'status'              => $status,
            'transbank_status'    => $response->getDetails()[0]->getStatus() ?? null,
            'transbank_response'  => json_encode($response),
        ]);

        if( !$insert ) {
            $transactionTable = Transaction::getTableName();
            $wpdb->show_errors();
            $errorMessage = "La transacción no se pudo registrar en la tabla: '{$transactionTable}', query: {$wpdb->last_query}, error: {$wpdb->last_error}";
            $this->logger->logInfo($errorMessage);
            //wc_add_notice($errorMessage, 'error');
            throw new Exception($errorMessage);
        }

        if ($response->isApproved() && $insert) {
            do_action('transbank_oneclick_transaction_approved', $order);

            return [
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            ];
        }

        do_action('transbank_oneclick_transaction_failed', $order);

        return [
            'result' => 'error',
        ];
    }

    /**
     * @param WC_Order $order
     * @param string   $message
     *
     * @throws \Exception
     */
    protected function failedRefund(WC_Order $order, string $message)
    {
        $order->add_order_note($message);

        throw new \Exception($message);
    }

    /**
     * @param WC_Order $order
     */
    private function setAfterPaymentOrderStatus(WC_Order $order){
        $status = $this->get_option('oneclick_after_payment_order_status');
        if ($status == ''){
            $order->payment_complete();
        }
        else{
            $order->payment_complete();
            $order->update_status($status);
        }
    }

}
