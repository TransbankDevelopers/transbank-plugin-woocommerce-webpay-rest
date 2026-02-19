<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use WC_Order;
use Throwable;
use WC_Payment_Tokens;
use Transbank\Plugin\Exceptions\EcommerceException;
use Transbank\WooCommerce\WebpayRest\Helpers\ErrorHelper;
use Transbank\WooCommerce\WebpayRest\Helpers\BlocksHelper;
use Transbank\WooCommerce\WebpayRest\Tokenization\WC_Payment_Token_Oneclick;
use Transbank\WooCommerce\WebpayRest\Controllers\StartOneclickController;

class AuthorizeOneclickController extends BaseAuthorizeOneclickController
{
    protected string $gatewayId;
    protected string $returnUrl;

    /**
     * Initializes the controller with the given gateway ID and return URL.
     */
    public function __construct(string $gatewayId, string $returnUrl)
    {
        parent::__construct();
        $this->gatewayId = $gatewayId;
        $this->returnUrl = $returnUrl;
    }

    /**
     * Process payment and return result.
     *
     * @param mixed $orderId
     *
     * @return array The result of the payment process, including success status and redirect URL.
     */
    public function process($orderId): array
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
        $paymentTokenId = isset($request["wc-{$this->gatewayId}-payment-token"])
            ? wc_clean($request["wc-{$this->gatewayId}-payment-token"])
            : null;

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
     * @throws EcommerceException
     */
    private function handleAuthorization(WC_Order $order, string $paymentTokenId)
    {
        $transaction = null;
        $orderNotes = '';
        try {
            $this->log->logInfo('Iniciando pago con Oneclick', ['tokenId' => $paymentTokenId]);

            $paymentToken = $this->getWcPaymentToken($paymentTokenId);
            $amount = $this->ecommerceService->getTotalAmountFromOrder($order);

            if (!$this->validatePayerMatchesCardInscription($paymentToken)) {
                throw new EcommerceException("Datos incorrectos para autorizar la transacción.");
            }

            $authorizeResponse = $this->authorizeTransaction($order->get_id(), $amount, $paymentToken);

            if (!$authorizeResponse->isApproved()) {
                $this->handleFailedAuthorization($order, $transaction, $authorizeResponse);
            }

            $order->add_payment_token($paymentToken);
            $this->ecommerceService->completeOneclickOrder($order);
            $this->emptyCart();

            $orderNotes = $this->getOrderNotesFromAuthorizeResponse($authorizeResponse, 'Oneclick: Pago exitoso');
            $order->add_order_note($orderNotes);

            do_action('wc_transbank_oneclick_transaction_approved', ['order' => $order->get_data()]);
            $this->log->logInfo('Se ha autorizado el pago correctamente', ['orderId' => $order->get_id()]);

            return [
                'result' => 'success',
                'redirect' => $this->returnUrl
            ];
        } catch (\Exception $e) {
            $this->shouldThrowException = true;
            $this->ecommerceService->setOneclickOrderAsFailed($order, $orderNotes);
            $this->log->logError('Error al autorizar', [
                'orderId' => $order->get_id(),
                'error' => $e->getMessage(),
            ]);
            if ($transaction) {
                $this->transactionService->updateWithAuthorizeResponseError(
                    $transaction->getId(),
                    'error',
                    $e->getMessage()
                );
            }
            do_action('wc_transbank_oneclick_transaction_failed', ['order' => $order->get_data()]);
            throw new EcommerceException($e->getMessage(), $e);
        }
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
