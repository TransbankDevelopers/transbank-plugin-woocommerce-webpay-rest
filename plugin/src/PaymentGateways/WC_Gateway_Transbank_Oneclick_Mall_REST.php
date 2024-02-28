<?php

namespace Transbank\WooCommerce\WebpayRest\PaymentGateways;

use Exception;
use Throwable;
use Transbank\Plugin\Exceptions\EcommerceException;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\Webpay\Options;
use Transbank\WooCommerce\WebpayRest\Controllers\OneclickInscriptionResponseController;
use Transbank\WooCommerce\WebpayRest\Helpers\ErrorHelper;
use Transbank\WooCommerce\WebpayRest\Helpers\BlocksHelper;
use Transbank\Plugin\Exceptions\Oneclick\RejectedAuthorizeOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\CreateTransactionOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\AuthorizeOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\ConstraintsViolatedAuthorizeOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\RejectedRefundOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\RefundOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\NotFoundTransactionOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\GetTransactionOneclickException;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkResponseUtil;
use Transbank\WooCommerce\WebpayRest\Tokenization\WC_Payment_Token_Oneclick;
use WC_Order;
use WC_Payment_Gateway_CC;
use WC_Payment_Tokens;

/**
 * Class WC_Gateway_Transbank_Oneclick_Mall_REST.
 */
class WC_Gateway_Transbank_Oneclick_Mall_REST extends WC_Payment_Gateway_CC
{
    use TransbankRESTPaymentGateway;

    const ID = 'transbank_oneclick_mall_rest';
    const WOOCOMMERCE_API_RETURN_ADD_PAYMENT = 'wc_gateway_transbank_oneclick_return_payments';

    const PAYMENT_GW_DESCRIPTION = 'Inscribe tu tarjeta de crédito, débito o prepago y luego paga ' .
        'con un solo click a través de Webpay Oneclick';

    /**
     * @var Transbank\Webpay\Oneclick\MallInscription
     */
    protected $oneclickInscription;
    protected $logger;
    /**
     * @var Transbank\WooCommerce\WebpayRest\OneclickTransbankSdk
     */
    protected $oneclickTransbankSdk;

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

        $this->id = self::ID;
        $this->title = 'Webpay Oneclick';
        $this->method_title = 'Webpay Oneclick';
        $this->description = $this->get_option('oneclick_payment_gateway_description', self::PAYMENT_GW_DESCRIPTION);
        $this->method_description =
            $this->get_option('oneclick_payment_gateway_description', self::PAYMENT_GW_DESCRIPTION);

        $this->icon = plugin_dir_url(dirname(dirname(__FILE__))) . 'images/oneclick.png';

        $this->init_form_fields();
        $this->init_settings();

        $this->logger = TbkFactory::createLogger();

        $this->max_amount = $this->get_option('max_amount') ?? 100000;
        $this->oneclickTransbankSdk = TbkFactory::createOneclickTransbankSdk();

        add_action(
            'woocommerce_scheduled_subscription_payment_' . $this->id,
            [$this, 'scheduled_subscription_payment'],
            10,
            3
        );
        add_action('woocommerce_api_' . strtolower(static::WOOCOMMERCE_API_RETURN_ADD_PAYMENT), [
            new OneclickInscriptionResponseController($this->id),
            'response',
        ]);

        add_filter('woocommerce_payment_methods_list_item', [$this, 'methods_list_item_oneclick'], null, 2);
        add_filter('woocommerce_payment_token_class', [$this, 'set_payment_token_class']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
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
        $order = null;
        try {
            $order = new WC_Order($order_id);
            $resp = $this->oneclickTransbankSdk->refundTransaction($order->get_id(), round($amount));
            $refundResponse = $resp['refundResponse'];
            $transaction = $resp['transaction'];
            $jsonResponse = json_encode($refundResponse, JSON_PRETTY_PRINT);
            $this->addRefundOrderNote($refundResponse, $order, $amount);
            do_action('transbank_oneclick_refund_finished', $order, $transaction, $jsonResponse);
            do_action('wc_transbank_oneclick_refund_approved', [
                'order' => $order->get_data(),
                'transbankTransaction' => $transaction
            ]);
            return true;
        } catch (GetTransactionOneclickException $e) {
            $errorMessage =
                'Se intentó anular transacción, pero hubo un problema obteniéndolo de la base de datos ' .
                'de transacciones de webpay plus.';

            $order->add_order_note($errorMessage);
            do_action('wc_transbank_oneclick_refund_failed', ['order' => $order->get_data()]);
            throw new EcommerceException($errorMessage, $e);
        } catch (NotFoundTransactionOneclickException $e) {
            $errorMessage =
                'Se intentó anular transacción, pero no se encontró en la base de datos de transacciones ' .
                'de webpay plus. ';

            $order->add_order_note($errorMessage);
            do_action('wc_transbank_oneclick_refund_failed', ['order' => $order->get_data()]);
            throw new EcommerceException($errorMessage, $e);
        } catch (RefundOneclickException $e) {
            $order->add_order_note('<strong>Error al anular:</strong><br />' . $e->getMessage());
            do_action('wc_transbank_oneclick_refund_failed',  [
                'order' => $order->get_data(),
                'transbankTransaction' => $e->getTransaction(),
                'errorMessage' => $e->getMessage()
            ]);
            throw new EcommerceException('Error al anular: ' . $e->getMessage(), $e);
        } catch (RejectedRefundOneclickException $e) {
            $errorMessage = "Anulación a través de Webpay FALLIDA.\n\n" .
                json_encode($e->getRefundResponse(), JSON_PRETTY_PRINT);

            $order->add_order_note($errorMessage);
            do_action('wc_transbank_oneclick_refund_failed', [
                'order' => $order->get_data(),
                'transbankTransaction' => $e->getTransaction()
            ]);
            throw new EcommerceException($errorMessage, $e);
        } catch (Throwable $e) {
            $order->add_order_note('Anulación a través de Webpay FALLIDA. ' . $e->getMessage());
            do_action('wc_transbank_oneclick_refund_failed', ['order' => $order->get_data()]);
            throw new EcommerceException('Anulación a través de Webpay fallida.', $e);
        }
    }

    public function admin_options()
    {
        if ($this->is_valid_for_use()) {
            $tab = 'options_oneclick';
            $environment = $this->get_option('environment');
            $showedWelcome = get_site_option('transbank_webpay_oneclick_rest_showed_welcome_message');
            update_site_option('transbank_webpay_oneclick_rest_showed_welcome_message', true);
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

    /**
     * @throws Transbank\Webpay\Oneclick\Exceptions\MallTransactionAuthorizeException
     */
    public function scheduled_subscription_payment($amount_to_charge, WC_Order $renewalOrder)
    {
        $this->logger->logInfo('New scheduled_subscription_payment for Order #' . $renewalOrder->get_id());
        $customerId = $renewalOrder->get_customer_id();
        if (!$customerId) {
            $this->logger->logError('There is no costumer id on the renewal order');

            throw new EcommerceException('There is no costumer id on the renewal order');
        }

        /** @var WC_Payment_Token_Oneclick $paymentToken */
        $paymentToken = WC_Payment_Tokens::get_customer_default_token($customerId);
        $response = $this->authorizeTransaction($renewalOrder, $paymentToken, $amount_to_charge);
        if ($response['result'] == 'error') {
            throw new EcommerceException('Se produjo un error en la autorización');
        }
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

    public function set_payment_token_class()
    {
        return WC_Payment_Token_Oneclick::class;
    }

    /**
     * Procesar pago y retornar resultado.
     **
     *
     * @throws Transbank\Webpay\Oneclick\Exceptions\MallTransactionAuthorizeException
     */
    public function process_payment($order_id)
    {
        $errorHookName = 'wc_gateway_transbank_process_payment_error_' . $this->id;
        $shouldThrowException = false;

        try {
            $order = new WC_Order($order_id);

            if (!$order->needs_payment() && !wcs_is_subscription($order_id)) {
                $this->logger->logError('This order was already paid or does not need payment');
                $errorMessage = __(
                    'Esta transacción puede ya estar pagada o encontrarse en un estado que no permite un nuevo pago. ',
                    'transbank_wc_plugin'
                );

                throw new EcommerceException($errorMessage);
            }

            $paymentMethodOption = $_POST["wc-{$this->id}-payment-token"] ?? null;
            $addNewCard = 'new' === $paymentMethodOption || $paymentMethodOption === null;
            $payWithSavedToken = $paymentMethodOption !== null && !$addNewCard;

            if (!get_current_user_id()) {
                $order->add_order_note(
                    'El usuario intentó pagar con oneclick pero no tiene (y no creó durante el checkout)' .
                        ' cuenta de usuario'
                );
                $this->logger->logInfo('Checkout: The user should have an account to add a new card. ');

                $errorMessage = __(
                    'Webpay Oneclick: Debes crear o tener una cuenta en el sitio para poder inscribir ' .
                        'tu tarjeta y usar este método de pago.',
                    'transbank_wc_plugin'
                );

                throw new EcommerceException($errorMessage);
            }

            if ($addNewCard) {
                $this->logger->logInfo('[Oneclick] Checkout: start inscription');

                $response = $this->start($order_id);

                $this->logger->logInfo('[Oneclick] Checkout: inscription response: ');
                $this->logger->logInfo(json_encode($response));
                $order->add_order_note('El usuario inició inscripción de nueva tarjeta. Redirigiendo a ' .
                    'formulario OneClick...');

                do_action('transbank_oneclick_adding_card_from_order', $order);

                return [
                    'result'   => 'success',
                    'redirect' => $response->getRedirectUrl(),
                ];
            }

            if ($payWithSavedToken) {

                $shouldThrowException = true;
                return $this->authorizeTransaction($order);
            }
            $errorMessage = __('Error interno: no se pudo procesar el pago', 'transbank_wc_plugin');
            throw new EcommerceException($errorMessage);
        } catch (\Throwable $exception) {
            $errorMessage = ErrorHelper::getErrorMessageBasedOnTransbankSdkException($exception);
            do_action($errorHookName, $exception, $shouldThrowException);
            BlocksHelper::addLegacyNotices($errorMessage, 'error');

            return [
                'result' => 'error',
                'redirect' => ''
            ];
        }
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws Transbank\Webpay\Oneclick\Exceptions\InscriptionStartException
     */
    public function add_payment_method()
    {
        $response = $this->start(null, 'my_account');
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
        $html = '<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
            <strong>Esta tarjeta se guardará en tu cuenta para que puedas volver a usarla.</strong>
            </p>';
        echo $html;
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

        $this->form_fields = [
            'enabled' => [
                'title'   => __('Activar/Desactivar', 'transbank_wc_plugin'),
                'type'    => 'checkbox',
                'default' => 'no',
            ],
            'environment' => [
                'title'       => __('Ambiente', 'transbank_wc_plugin'),
                'type'        => 'select',
                'desc_tip'    => $environmentDescription,
                'options' => [
                    Options::ENVIRONMENT_INTEGRATION => __('Integración', 'transbank_wc_plugin'),
                    Options::ENVIRONMENT_PRODUCTION  => __('Producción', 'transbank_wc_plugin'),
                ],
                'default' => Options::ENVIRONMENT_INTEGRATION,
            ],
            'commerce_code' => [
                'title'       => __('Código de Comercio Mall Producción', 'transbank_wc_plugin'),
                'placeholder' => 'Ej: 597012345678',
                'desc_tip'    => $commerceCodeDescription,
                'type'        => 'text',
                'default'     => '',
            ],
            'child_commerce_code' => [
                'title'       => __('Código de Comercio Tienda Producción', 'transbank_wc_plugin'),
                'placeholder' => 'Ej: 597012345678',
                'desc_tip'    => $childCommerceCodeDescription,
                'type'        => 'text',
                'default'     => '',
            ],
            'api_key' => [
                'title'       => __('API Key (llave secreta) producción', 'transbank_wc_plugin'),
                'type'        => 'text',
                'placeholder' => 'Ej: XXXXXXXXXXXXXXXXXXXXXXXXXXXX',
                'desc_tip'    => $apiKeyDescription,
                'default'     => '',
            ],
            'max_amount' => [
                'title'       => __('Monto máximo de transacción permitido', 'transbank_wc_plugin'),
                'type'        => 'number',
                'options'     => ['step' => 100],
                'default'     => '0',
                'desc_tip'    => $maxAmountDescription,
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
            'oneclick_payment_gateway_description' => [
                'title'       => __('Descripción', 'transbank_wc_plugin'),
                'type'        => 'textarea',
                'desc_tip'    => 'Define la descripción del medio de pago.',
                'default' => self::PAYMENT_GW_DESCRIPTION,
                'class' => 'admin-textarea'
            ]
        ];
    }

    protected function add_order_notes(WC_Order $wooCommerceOrder, $response, $message)
    {
        $firstDetail = $response->getDetails()[0];
        $formattedAmount = TbkResponseUtil::getAmountFormatted($firstDetail->getAmount());
        $status = TbkResponseUtil::getStatus($firstDetail->getStatus());
        $paymentType = TbkResponseUtil::getPaymentType($firstDetail->getPaymentTypeCode());
        $installmentType = TbkResponseUtil::getInstallmentType($firstDetail->getPaymentTypeCode());
        $formattedAccountingDate = TbkResponseUtil::getAccountingDate($response->getAccountingDate());
        $formattedDate = TbkResponseUtil::transactionDateToLocalDate($response->getTransactionDate());
        $installmentAmount = $firstDetail->getInstallmentsAmount() ?? 0;
        $formattedInstallmentAmount = TbkResponseUtil::getAmountFormatted($installmentAmount);

        $transactionDetails = "
            <div class='transbank_response_note'>
                <p><h3>{$message}</h3></p>

                <strong>Estado: </strong>{$status} <br />
                <strong>Orden de compra mall: </strong>{$response->getBuyOrder()} <br />
                <strong>Orden de compra tienda: </strong>{$firstDetail->getBuyOrder()} <br />
                <strong>Código de autorización: </strong>{$firstDetail->getAuthorizationCode()} <br />
                <strong>Últimos dígitos tarjeta: </strong>{$response->getCardNumber()} <br />
                <strong>Monto: </strong>{$formattedAmount} <br />
                <strong>Código de respuesta: </strong>{$firstDetail->getResponseCode()} <br />
                <strong>Tipo de pago: </strong>{$paymentType} <br />
                <strong>Tipo de cuota: </strong>{$installmentType} <br />
                <strong>Número de cuotas: </strong>{$firstDetail->getInstallmentsNumber()} <br />
                <strong>Monto de cada cuota: </strong>{$formattedInstallmentAmount} <br />
                <strong>Fecha:</strong> {$formattedDate} <br />
                <strong>Fecha contable:</strong> {$formattedAccountingDate} <br />
            </div>
        ";
        $wooCommerceOrder->add_order_note($transactionDetails);
        $wooCommerceOrder->add_meta_data('transbank_response', json_encode($response));
    }

    /**
     * @param int|null $orderId
     * @param string   $from
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws Transbank\Webpay\Oneclick\Exceptions\InscriptionStartException
     *
     * @return Transbank\Webpay\Oneclick\Responses\InscriptionStartResponse
     */
    public function start(
        int $orderId = null,
        string $from = 'checkout'
    ) {
        // The user selected Oneclick, Pay with new card and choosed to save it in their account.
        $userInfo = wp_get_current_user();
        $returnUrl = add_query_arg('wc-api', static::WOOCOMMERCE_API_RETURN_ADD_PAYMENT, home_url('/'));
        $email = $userInfo->user_email; // Todo: check if we had to generate a random email as well
        return $this->oneclickTransbankSdk->startInscription($orderId, $userInfo->ID, $email, $returnUrl, $from);
    }

    /**
     * @param WC_Payment_Token_Oneclick $paymentToken
     *
     * @return WC_Payment_Token_Oneclick
     */
    private function getWcPaymentToken(WC_Payment_Token_Oneclick $paymentToken = null)
    {
        if ($paymentToken) {
            return $paymentToken;
        } else {
            $tokenId = wc_clean($_POST["wc-{$this->id}-payment-token"]);
            /** @var WC_Payment_Token_Oneclick $token */
            return \WC_Payment_Tokens::get($tokenId);
        }
    }

    private function getAmountForAuthorize($amount, $order)
    {
        if ($amount == null) {
            $amount = (int) number_format($order->get_total(), 0, ',', '');
        }
        return $amount;
    }

    /**
     * @param WC_Order $order
     *
     * @throws Transbank\Webpay\Oneclick\Exceptions\MallTransactionAuthorizeException
     *
     * @return array
     */
    public function authorizeTransaction(
        WC_Order $order,
        WC_Payment_Token_Oneclick $paymentToken = null,
        $amount = null
    ): array {

        try {

            $token = $this->getWcPaymentToken($paymentToken);
            $this->logger->logInfo('[Oneclick] Checkout: paying with token ID #' . $token->get_id());

            $amount = $this->getAmountForAuthorize($amount, $order);
            $authorizeResponse =
            $this->oneclickTransbankSdk->authorize(
                $order->get_id(),
                $amount, $token->get_username(),
                $token->get_token()
            );

            $order->add_payment_token($token);
            $this->setAfterPaymentOrderStatus($order);
            if (wc()->cart) {
                wc()->cart->empty_cart();
            }
            $this->add_order_notes($order, $authorizeResponse, 'Oneclick: Pago exitoso');
            do_action('wc_transbank_oneclick_transaction_approved', ['order' => $order->get_data()]);
            return [
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            ];
        } catch (CreateTransactionOneclickException $e) {
            $order->update_status('failed');
            $order->add_order_note('Problemas al crear el registro de Transacción');
        } catch (AuthorizeOneclickException $e) {
            $order->update_status('failed');
            $order->add_order_note('Transacción con problemas de autorización');
        } catch (RejectedAuthorizeOneclickException $e) {
            $order->update_status('failed');
            $this->add_order_notes($order, $e->getAuthorizeResponse(), 'Oneclick: Pago rechazado');
            $order->add_order_note('Transacción rechazada');
        } catch (ConstraintsViolatedAuthorizeOneclickException $e) {
            $order->update_status('failed');
            $this->add_order_notes($order, $e->getAuthorizeResponse(), 'Oneclick: Pago rechazado');
            $order->add_order_note('CONSTRAINTS_VIOLATED: ' . $e->getMessage());
        }

        do_action('wc_transbank_oneclick_transaction_failed', ['order' => $order->get_data()]);
        throw $e;
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
    private function setAfterPaymentOrderStatus(WC_Order $order)
    {
        $status = $this->get_option('oneclick_after_payment_order_status');
        if ($status == '') {
            $order->payment_complete();
        } else {
            $order->payment_complete();
            $order->update_status($status);
        }
    }
}
