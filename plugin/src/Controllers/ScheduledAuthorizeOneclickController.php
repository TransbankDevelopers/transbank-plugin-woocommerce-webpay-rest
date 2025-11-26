<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use WC_Order;
use Throwable;
use WC_Payment_Tokens;
use Transbank\Plugin\Exceptions\EcommerceException;
use Transbank\WooCommerce\WebpayRest\Tokenization\WC_Payment_Token_Oneclick;

class ScheduledAuthorizeOneclickController extends BaseAuthorizeOneclickController
{

    /**
     * Processes a scheduled subscription payment.
     *
     * This method authorizes a scheduled subscription payment for the given renewal order. It retrieves the customer ID
     * from the renewal order, obtains the customer's default payment token and authorizes the payment with Oneclick.
     *
     * @param float $amount The amount to charge for the subscription payment.
     * @param WC_Order $order The renewal order object for the subscription.
     *
     * @throws EcommerceException If there is no customer ID on the renewal order.
     */
    public function process($amount, WC_Order $order)
    {
        $transaction = null;
        $orderNotes = '';
        try {

            $this->log->logInfo('Autorizando suscripción', ['order' => $order->get_id()]);
            $customerId = $this->getCustomerIdOrFail($order);
            $paymentToken = $this->getDefaultPaymentTokenOrFail($customerId);

            $authorizeResponse = $this->authorizeTransaction($order->get_id(), $amount, $paymentToken);

            if (!$authorizeResponse->isApproved()) {
                $this->handleFailedAuthorization($order, $transaction, $authorizeResponse);
            }

            $order->add_payment_token($paymentToken);
            $orderNotes = $this->getOrderNotesFromAuthorizeResponse($authorizeResponse, 'Oneclick: Pago de suscripción exitoso');
            $order->add_order_note($orderNotes);
            do_action('wc_transbank_oneclick_transaction_approved', ['order' => $order->get_data()]);
            $this->ecommerceService->completeOneclickOrder($order);
            $this->log->logInfo('Suscripción autorizada correctamente', ['order' => $order->get_id()]);

        } catch (Throwable $e) {
            $this->log->logError("Error al procesar suscripción", ['error' => $e->getMessage()]);
            $logsUrl = admin_url('admin.php?page=transbank_webpay_plus_rest&tbk_tab=logs');
            $this->ecommerceService->setOneclickOrderAsFailed($order, 'Error al procesar suscripción, para más detalles revisar el archivo de <a href=" ' . $logsUrl . '">logs</a>.');
            if ($transaction) {
                $this->transactionService->updateWithAuthorizeResponseError(
                    $transaction->getId(),
                    'error en suscripción',
                    $e->getMessage()
                );
            }
        }
    }

    private function getCustomerIdOrFail(WC_Order $order): int
    {
        $customerId = $order->get_customer_id();
        if (!$customerId) {
            $this->log->logError('Falta ID de cliente en suscripción.');
            throw new EcommerceException('No se encontró ID de cliente en la orden de suscripción.');
        }
        return $customerId;
    }

    private function getDefaultPaymentTokenOrFail(int $customerId): WC_Payment_Token_Oneclick
    {
        /** @var WC_Payment_Token_Oneclick|null $token */
        $token = WC_Payment_Tokens::get_customer_default_token($customerId);
        if (!$token) {
            $this->log->logError('No se encontró token por defecto', ['client' => $customerId]);
            throw new EcommerceException('No se encontró un método de pago activo para la suscripción.');
        }
        return $token;
    }
}
