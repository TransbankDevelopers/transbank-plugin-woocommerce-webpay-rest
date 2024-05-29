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
use Transbank\WooCommerce\WebpayRest\OneclickTransbankSdk;
use Transbank\Webpay\Oneclick\Exceptions\MallTransactionAuthorizeException;
use Transbank\Webpay\Oneclick\Exceptions\InscriptionStartException;
use Transbank\Webpay\Oneclick\Responses\InscriptionStartResponse;
use Transbank\Webpay\Oneclick\Responses\MallTransactionAuthorizeResponse;
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
     * @var OneclickTransbankSdk
     */
    protected $oneclickTransbankSdk;

    /**
     * Indicates if the exception message should be displayed in the notice when checkout block is enabled.
     *
     * @var bool
     */
    private $shouldThrowException;

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
        $this->shouldThrowException = false;

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
        add_filter('woocommerce_payment_token_class', [$this, 'getOneclickPaymentTokenClass']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
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
        try {
            $order = new WC_Order($order_id);

            $this->checkOrderCanBePaid($order);
            $this->checkUserIsLoggedIn();

            return $this->handleOneclickPayment($_POST, $order);
        } catch (Throwable $exception) {
            $errorHookName = 'wc_gateway_transbank_process_payment_error_' . $this->id;
            $errorMessage = ErrorHelper::getErrorMessageBasedOnTransbankSdkException($exception);
            do_action($errorHookName, $exception, true);
            BlocksHelper::addLegacyNotices($errorMessage, 'error');

            return [
                'result' => 'error',
                'redirect' => ''
            ];
        }
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
        try {
            $this->logger->logInfo('Autorizando suscripción para la orden #' . $renewalOrder->get_id());
            $customerId = $renewalOrder->get_customer_id();

            if (!$customerId) {
                $this->logger->logError('No existe el ID de usuario en la suscripción.');
                throw new EcommerceException('There is no costumer id on the renewal order');
            }

            /** @var WC_Payment_Token_Oneclick $paymentToken */
            $paymentToken = WC_Payment_Tokens::get_customer_default_token($customerId);

            $authorizeResponse = $this->oneclickTransbankSdk->authorize(
                $renewalOrder->get_id(),
                $amount_to_charge,
                $paymentToken->get_username(),
                $paymentToken->get_token()
            );

            $renewalOrder->add_payment_token($paymentToken);

            $orderNotes = $this->getOrderNotesFromAuthorizeResponse($authorizeResponse, 'Oneclick: Pago de suscripción exitoso');
            $renewalOrder->add_order_note($orderNotes);

            do_action('wc_transbank_oneclick_transaction_approved', ['order' => $renewalOrder->get_data()]);

            $this->setOrderAsComplete($renewalOrder);

            $this->logger->logInfo('Suscripción autorizada correctamente para la orden #' . $renewalOrder->get_id());
        } catch (Throwable $ex) {
            $this->logger->logError("Error al procesar suscripción: " . $ex->getMessage());
            $renewalOrder->add_order_note('Error al procesar suscripción, para más detalles revisar el archivo log.');
        }
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

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws InscriptionStartException
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
     * Handles the request for processing a payment or initiating a new card inscription.
     *
     * This method determines whether to process a payment authorization or initiate a new card inscription
     * based on the provided request data. If a new payment token ID is provided or if no token ID is provided,
     * it initiates the inscription process. Otherwise, it handles the authorization process for the provided
     * payment token ID.
     *
     * @param array $request The request data containing payment token information.
     * @param WC_Order $order The WooCommerce order object associated with the request.
     *
     * @return array The result of the processing, including a success message and redirect URL.
     */
    private function handleOneclickPayment(array $request, WC_Order $order)
    {
        $paymentTokenId = wc_clean($request["wc-{$this->id}-payment-token"]) ?? null;

        if ($paymentTokenId === 'new' || is_null($paymentTokenId)) {
            return $this->handleInscription($order);
        }

        return $this->handleAuthorization($order, $paymentTokenId);
    }

    /**
     * Handles the inscription process for adding a new card.
     *
     * This method initiates the inscription process for adding a new card to the OneClick payment method.
     *
     * @param WC_Order $order The WooCommerce order object.
     *
     * @return array The result of the inscription process, including a success message and redirect URL.
     */
    private function handleInscription(WC_Order $order)
    {
        $this->logger->logInfo('[Oneclick] Inicio de inscripción');

        $response = $this->start($order->get_id());

        $this->logger->logInfo('[Oneclick] Respuesta de inscripción: ');
        $this->logger->logInfo(json_encode($response));
        $order->add_order_note('El usuario inició inscripción de nueva tarjeta. Redirigiendo a formulario OneClick.');

        do_action('transbank_oneclick_adding_card_from_order', $order);

        return [
            'result'   => 'success',
            'redirect' => $response->getRedirectUrl(),
        ];
    }

    /**
     * Handles the authorization process for a OneClick payment.
     *
     * This method performs the authorization process for a OneClick payment.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @param string $paymentTokenId The ID of the payment token associated with the order.
     *
     * @return array The result of the authorization process, including a success message and redirect URL.
     *
     * @throws CreateTransactionOneclickException If there are issues creating the transaction.
     * @throws AuthorizeOneclickException If there are problems with authorization.
     * @throws RejectedAuthorizeOneclickException If the authorization is rejected.
     * @throws ConstraintsViolatedAuthorizeOneclickException If constraints are violated during authorization.
     */
    private function handleAuthorization(WC_Order $order, string $paymentTokenId)
    {
        try {
            $orderNotes = '';
            $this->logger->logInfo('[Oneclick] Checkout: pagando con el token ID #' . $paymentTokenId);
            $paymentToken = $this->getWcPaymentToken($paymentTokenId);
            $amount = $this->getTotalAmountFromOrder($order);

            $authorizeResponse = $this->oneclickTransbankSdk->authorize(
                $order->get_id(),
                $amount,
                $paymentToken->get_username(),
                $paymentToken->get_token()
            );

            $order->add_payment_token($paymentToken);
            $this->setOrderAsComplete($order);
            $this->emptyCart();

            $orderNotes = $this->getOrderNotesFromAuthorizeResponse($authorizeResponse, 'Oneclick: Pago exitoso');
            $order->add_order_note($orderNotes);

            do_action('wc_transbank_oneclick_transaction_approved', ['order' => $order->get_data()]);

            $this->logger->logInfo('Se ha autorizado el pago correctamente para la orden #' . $order->get_id());

            return [
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            ];
        } catch (CreateTransactionOneclickException $e) {
            $orderNotes = 'Transacción con problemas de autorización';
        } catch (AuthorizeOneclickException $e) {
            $orderNotes = 'Problemas al crear el registro de Transacción';
        } catch (RejectedAuthorizeOneclickException $e) {
            $response = $e->getAuthorizeResponse();
            $orderNotes = $this->getOrderNotesFromAuthorizeResponse(
                $response,
                'Oneclick: Pago rechazado'
            );
            $order->add_meta_data('transbank_response', json_encode($response));
        } catch (ConstraintsViolatedAuthorizeOneclickException $e) {
            $response = $e->getAuthorizeResponse();
            $orderNotes = $this->getOrderNotesFromAuthorizeResponse(
                $response,
                'Oneclick: Pago rechazado'
            );
            $order->add_order_note($e->getMessage());
            $order->add_meta_data('transbank_response', json_encode($response));
        } finally {
            if (isset($e)) {
                $this->shouldThrowException = true;
                $this->setOrderAsFailed($order, $orderNotes);
                do_action('wc_transbank_oneclick_transaction_failed', ['order' => $order->get_data()]);
                $this->logger->logError('Error al autorizar: ' . $e->getMessage());
                throw $e;
            }
        }
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
                'type'        => 'password',
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

    protected function getOrderNotesFromAuthorizeResponse(MallTransactionAuthorizeResponse $response, string $orderNotesTitle)
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

        return "
            <div class='transbank_response_note'>
                <p><h3>{$orderNotesTitle}</h3></p>

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
    }

    /**
     * @param int|null $orderId
     * @param string   $from
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws InscriptionStartException
     *
     * @return InscriptionStartResponse
     */
    public function start(
        int $orderId = null,
        string $from = 'checkout'
    ) {
        // The user selected Oneclick, Pay with new card and choosed to save it in their account.
        $userInfo = wp_get_current_user();
        $returnUrl = add_query_arg('wc-api', static::WOOCOMMERCE_API_RETURN_ADD_PAYMENT, home_url('/'));
        $email = $userInfo->user_email;
        return $this->oneclickTransbankSdk->startInscription($orderId, $userInfo->ID, $email, $returnUrl, $from);
    }

    /**
     * Checks if the order can be paid.
     *
     * This method verifies whether an order requires payment or if it's already paid.
     * It logs an error and throws an EcommerceException if the order does not need payment or if it cannot be paid again.
     *
     * @param int $order_id The ID of the order to check.
     * @throws EcommerceException If the order does not need payment or is in a state that does not allow a new payment.
     */
    private function checkOrderCanBePaid(WC_Order $order)
    {
        if (!$order->needs_payment() && !wcs_is_subscription($order->get_id())) {
            $this->logger->logError('La orden se encuentra en un estado en la que no puede ser pagada.');
            $errorMessage = __(
                'Esta transacción puede ya estar pagada o encontrarse en un estado que no permite un nuevo pago. ',
                'transbank_wc_plugin'
            );

            throw new EcommerceException($errorMessage);
        }
    }

    /**
     * Checks if the user is logged in before allowing card registration.
     *
     * This method verifies whether the user is logged in before allowing them to add a new card.
     * It logs an informational message and throws an EcommerceException if the user is not logged in.
     *
     * @throws EcommerceException If the user is not logged in.
     */
    private function checkUserIsLoggedIn()
    {
        // Check if the user is logged in
        if (!is_user_logged_in()) {
            $this->logger->logInfo('El usuario debe tener una cuenta creada para poder inscribir una tarjeta.');
            $errorMessage = __(
                'Webpay Oneclick: Debes crear o tener una cuenta en el sitio para poder inscribir ' .
                    'tu tarjeta y usar este método de pago.',
                'transbank_wc_plugin'
            );

            throw new EcommerceException($errorMessage);
        }
    }

    /**
     * Retrieves a WC_Payment_Token_Oneclick object by its token ID.
     *
     * This method retrieves a payment token of type WC_Payment_Token_Oneclick using its ID.
     *
     * @param string $paymentTokenId The ID of the payment token to retrieve.
     * @return WC_Payment_Token_Oneclick Returns the payment token object.
     */
    private function getWcPaymentToken(string $paymentTokenId): WC_Payment_Token_Oneclick
    {
        return WC_Payment_Tokens::get($paymentTokenId);
    }

    /**
     * Retrieves the total amount from an order as an integer.
     *
     * This method takes a WC_Order object, gets its total amount, formats it to remove any decimal places,
     * and then converts it to an integer.
     *
     * @param WC_Order $order The order object from which to retrieve the total amount.
     * @return int The total amount of the order as an integer.
     */
    private function getTotalAmountFromOrder(WC_Order $order): int
    {
        return (int) number_format($order->get_total(), 0, ',', '');
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
     * Marks the given order as complete and updates its status if specified.
     *
     * This method sets the order's payment status to complete and then updates
     * the order status based on the configured option 'oneclick_after_payment_order_status'.
     * If no status is specified, the order is marked as complete without changing the status.
     *
     * @param WC_Order $order The order object to update.
     */
    private function setOrderAsComplete(WC_Order $order)
    {
        $status = $this->get_option('oneclick_after_payment_order_status');
        if (empty($status)) {
            $order->payment_complete();
        } else {
            $order->payment_complete();
            $order->update_status($status);
        }
    }

    /**
     * Sets the given order as failed and adds a note to the order.
     *
     * This method updates the status of the provided WC_Order object to 'failed' and adds a custom note to the order.
     *
     * @param WC_Order $order The order object to update.
     * @param string $orderNotes The custom note to add to the order.
     */
    private function setOrderAsFailed(WC_Order $order, string $orderNotes)
    {
        $order->update_status('failed');
        $order->add_order_note($orderNotes);
    }

    /**
     * Empties the WooCommerce cart.
     *
     * This method checks if the WooCommerce cart exists and then empties it.
     * If the cart exists, all items in the cart are removed.
     */
    private function emptyCart()
    {
        if (wc()->cart) {
            wc()->cart->empty_cart();
        }
    }
}
