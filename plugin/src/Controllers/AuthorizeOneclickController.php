<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use WC_Order;
use Throwable;
use WC_Payment_Tokens;
use Transbank\Plugin\Exceptions\EcommerceException;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Helpers\ErrorHelper;
use Transbank\WooCommerce\WebpayRest\Helpers\BlocksHelper;
use Transbank\Plugin\Exceptions\Oneclick\RejectedAuthorizeOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\CreateTransactionOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\AuthorizeOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\ConstraintsViolatedAuthorizeOneclickException;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkResponseUtil;
use Transbank\WooCommerce\WebpayRest\Tokenization\WC_Payment_Token_Oneclick;
use Transbank\Webpay\Oneclick\Exceptions\MallTransactionAuthorizeException;
use Transbank\Webpay\Oneclick\Responses\MallTransactionAuthorizeResponse;
use Transbank\WooCommerce\WebpayRest\Controllers\StartOneclickController;
use Transbank\Plugin\Services\TransactionService;
use Transbank\Plugin\Services\OneclickService;
use Transbank\Plugin\Helpers\ILogger;
use Transbank\WooCommerce\WebpayRest\Services\EcommerceService;
use Transbank\Plugin\Helpers\TbkConstants;

class AuthorizeOneclickController
{
    protected ILogger $log;
    protected TransactionService $transactionService;
    protected OneclickService $oneclickService;
    protected EcommerceService $ecommerceService;
    protected string $gatewayId;
    protected string $returnUrl;

    /**
     * Constructor initializes the logger.
     */
    public function __construct(string $gatewayId, string $returnUrl)
    {
        $this->log = TbkFactory::createLogger();
        $this->transactionService = TbkFactory::createTransactionService();
        $this->oneclickService = TbkFactory::createOneclickService();
        $this->ecommerceService = TbkFactory::createEcommerceService();
        $this->gatewayId = $gatewayId;
        $this->returnUrl = $returnUrl;
    }


    /**
     * Procesar pago y retornar resultado.
     **
     *
     * @throws MallTransactionAuthorizeException
     */
    public function proccess($orderId)
    {
        try {
            $order = $this->ecommerceService->getOrderById($orderId);
            $this->checkOrderCanBePaid($order);
            $this->checkUserIsLoggedIn();

            return $this->handleOneclickPayment($_POST, $order);
        } catch (Throwable $exception) {
            $errorHookName = 'wc_gateway_transbank_process_payment_error_' . $this->gatewayId;
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
    public function scheduledSubscriptionPayment($amount_to_charge, WC_Order $renewalOrder)
    {
        try {
            $this->log->logInfo('Autorizando suscripción para la orden #' . $renewalOrder->get_id());
            $customerId = $renewalOrder->get_customer_id();

            if (!$customerId) {
                $this->log->logError('No existe el ID de usuario en la suscripción.');
                throw new EcommerceException('There is no costumer id on the renewal order');
            }

            /** @var WC_Payment_Token_Oneclick $paymentToken */
            $paymentToken = WC_Payment_Tokens::get_customer_default_token($customerId);

            $transaction = $this->oneclickService->prepareTransaction($renewalOrder->get_id(), $amount_to_charge);
            $tx = $this->transactionService->create($transaction);
            $authorizeResponse = $this->oneclickService->authorize(
                $paymentToken->get_username(),
                $paymentToken->get_token(),
                $transaction->getBuyOrder(),
                $transaction->getChildBuyOrder(),
                $transaction->getAmount()
            );

            $this->transactionService->updateWithAuthorizeResponse($tx->id,$authorizeResponse);

            $renewalOrder->add_payment_token($paymentToken);

            $orderNotes = $this->getOrderNotesFromAuthorizeResponse($authorizeResponse, 'Oneclick: Pago de suscripción exitoso');
            $renewalOrder->add_order_note($orderNotes);

            do_action('wc_transbank_oneclick_transaction_approved', ['order' => $renewalOrder->get_data()]);

            $this->ecommerceService->completeOneclickOrder($renewalOrder);

            $this->log->logInfo('Suscripción autorizada correctamente para la orden #' . $renewalOrder->get_id());
        } catch (Throwable $ex) {
            $this->log->logError("Error al procesar suscripción: " . $ex->getMessage());
            $logsUrl = admin_url('admin.php?page=transbank_webpay_plus_rest&tbk_tab=logs');
            $this->ecommerceService->setOneclickOrderAsFailed($renewalOrder, 'Error al procesar suscripción, para más detalles revisar el archivo de <a href=" ' . $logsUrl . '">logs</a>.');
        }
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
        $paymentTokenId = wc_clean($request["wc-{$this->gatewayId}-payment-token"]) ?? null;

        if ($paymentTokenId === 'new' || is_null($paymentTokenId)) {
            return (new StartOneclickController())->handleInscription($order);
        }

        return $this->handleAuthorization($order, $paymentTokenId);
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
            $this->log->logInfo('[Oneclick] Checkout: pagando con el token ID #' . $paymentTokenId);
            $paymentToken = $this->getWcPaymentToken($paymentTokenId);
            $amount = $this->ecommerceService->getTotalAmountFromOrder($order);

            if (!$this->validatePayerMatchesCardInscription($paymentToken)) {
                throw new EcommerceException("Datos incorrectos para autorizar la transacción.");
            }

            $transaction = $this->transactionService->create(
                $this->oneclickService->prepareTransaction($order->get_id(), $amount)
            );

            $authorizeResponse = $this->oneclickService->authorize(
                $paymentToken->get_username(),
                $paymentToken->get_token(),
                $transaction->getBuyOrder(),
                $transaction->getChildBuyOrder(),
                $transaction->getAmount()
            );

            $this->transactionService->updateWithAuthorizeResponse($transaction->id,$authorizeResponse);

            if (!$authorizeResponse->isApproved()) {
                $this->log->logError("Transacción con autorización rechazada => parentBuyOrder:
                    {$transaction->getBuyOrder()}, childBuyOrder: {$transaction->getChildBuyOrder()}");
                $this->log->logError(json_encode($authorizeResponse));
                $orderNotes = $this->getOrderNotesFromAuthorizeResponse(
                    $authorizeResponse,
                    'Oneclick: Pago rechazado'
                );
                if ($authorizeResponse->getDetails()[0]->getStatus() === 'CONSTRAINTS_VIOLATED') {
                    $errorMessage = 'La transacción ha sido rechazada porque se superó el monto máximo por transacción, el monto máximo diario o el número de transacciones diarias configuradas por el comercio para cada usuario';
                    $order->add_order_note($errorMessage);
                }
                else {
                    $errorCode = $authorizeResponse->getDetails()[0]->getResponseCode() ?? null;
                    $errorMessage = 'La transacción ha sido rechazada (Código de error: ' . $errorCode . ')';
                }
                $order->add_meta_data('transbank_response', json_encode($authorizeResponse));
                throw new EcommerceException($errorMessage);
            }

            $order->add_payment_token($paymentToken);
            $this->ecommerceService->completeOneclickOrder($order);
            $this->emptyCart();

            $orderNotes = $this->getOrderNotesFromAuthorizeResponse($authorizeResponse, 'Oneclick: Pago exitoso');
            $order->add_order_note($orderNotes);

            do_action('wc_transbank_oneclick_transaction_approved', ['order' => $order->get_data()]);

            $this->log->logInfo('Se ha autorizado el pago correctamente para la orden #' . $order->get_id());

            return [
                'result'   => 'success',
                'redirect' => $this->returnUrl
            ];
        } catch (\Exception $e) {
            $this->shouldThrowException = true;
            $this->ecommerceService->setOneclickOrderAsFailed($order, $orderNotes);
            do_action('wc_transbank_oneclick_transaction_failed', ['order' => $order->get_data()]);
            $this->log->logError('Error al autorizar: ' . $e->getMessage());
            throw $e;
        }
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

    /**
     * Validate that the user paying for the order is the same as the one who registered the card.
     *
     * @param WC_Payment_Token_Oneclick $inscriptionData The card inscription data.
     *
     * @return bool True if the payer matches the card inscription, false otherwise.
     */
    private function validatePayerMatchesCardInscription(WC_Payment_Token_Oneclick $paymentToken): bool
    {
        $currentUser = wp_get_current_user();
        $userId = $currentUser->id;
        $inscriptionId = $paymentToken->get_userId();

        return $userId == $inscriptionId;
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
            $this->log->logInfo('El usuario debe tener una cuenta creada para poder inscribir una tarjeta.');
            $errorMessage = __(
                'Webpay Oneclick: Debes crear o tener una cuenta en el sitio para poder inscribir ' .
                    'tu tarjeta y usar este método de pago.',
                'transbank_wc_plugin'
            );

            throw new EcommerceException($errorMessage);
        }
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
            $this->log->logError('La orden se encuentra en un estado en la que no puede ser pagada.');
            $errorMessage = __(
                'Esta transacción puede ya estar pagada o encontrarse en un estado que no permite un nuevo pago. ',
                'transbank_wc_plugin'
            );

            throw new EcommerceException($errorMessage);
        }
    }
}
