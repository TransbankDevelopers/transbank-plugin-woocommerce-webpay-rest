<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Transbank\Webpay\Oneclick\MallInscription;
use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;
use Transbank\WooCommerce\WebpayRest\Models\Inscription;
use WC_Payment_Token_Oneclick;
use WC_Payment_Tokens;

class OneclickInscriptionResponseController
{
    protected $logger;
    protected $oneclickInscription;
    protected $gatewayId;

    /**
     * OneclickInscriptionResponseController constructor.
     */
    public function __construct(MallInscription $oneclickInscription, $gatewayId, $logger = null)
    {
        $this->logger = $logger ?? new LogHandler();
        $this->oneclickInscription = $oneclickInscription;
        $this->gatewayId = $gatewayId;
    }

    /**
     * @throws \Transbank\WooCommerce\WebpayRest\Exceptions\TokenNotFoundOnDatabaseException
     */
    public function response()
    {
        $this->logger->logInfo('[ONECLICK] Process inscription return: GET '.print_r($_GET, true).' | POST: '.print_r($_POST, true));
        if ($this->transactionWasTimeout()) {
            $this->logger->logError('[ONECLICK] Timeout Error'.print_r($_GET, true).print_r($_POST, true));
            wc_add_notice('La transacción fue cancelada automáticamente por estar inactivo mucho tiempo en el formulario de pago de Webpay. Puede reintentar el pago', 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }
        $tbkToken = $_GET['TBK_TOKEN'] ?? $_POST['TBK_TOKEN'] ?? null;
        if (!$tbkToken) {
            $this->logger->logError('No se puede acceder a esta página directamente');
            exit;
        }

        try {
            $inscription = Inscription::getByToken($tbkToken);
        } catch (\Exception $e) {
            $this->logger->logError($e->getMessage());

            throw $e;
        }

        $order = null;
        if ($inscription->order_id) {
            $order = new \WC_Order($inscription->order_id);
        }

        $from = $inscription->from;
        $tbkOrdenCompra = $_GET['TBK_ORDEN_COMPRA'] ?? $_POST['TBK_ORDEN_COMPRA'] ?? null;

        if ($tbkOrdenCompra) {
            // TODO: Mejorar este caso marcando la inscripción como abortada
            wc_add_notice('Has anulado la inscripción', 'warning');
            $this->logger->logError('La inscripción fue anulada por el usuario o hubo un error en el formulario de pago');
            if ($order) {
                $order->add_order_note('El usuario canceló la inscripción en el formulario de pago');
                $params = ['transbank_cancelled_order' => 1];
                $redirectUrl = add_query_arg($params, wc_get_checkout_url());
                wp_safe_redirect($redirectUrl);
                exit;
            }
            $this->redirectUser($from);
        }

        if ($inscription->status !== Inscription::STATUS_INITIALIZED) {
            $this->logger->logError('La inscripción no se encuentra en estado inicializada: '.$tbkToken);
            $this->redirectUser($from);
        }

        try {
            $response = $this->oneclickInscription->finish($tbkToken);
        } catch (\Exception $e) {
            $this->logger->logError('Ocurrió un error al ejecutar la inscripción: '.$e->getMessage());
            wc_add_notice('Ocurrió un error en la inscripción de la tarjeta: '.$e->getMessage(), 'error');
            $this->redirectUser($from);
            Inscription::update($inscription->id, [
                'status' => Inscription::STATUS_FAILED,
            ]);

            return;
        }

        Inscription::update($inscription->id, [
            'finished'           => true,
            'authorization_code' => $response->getAuthorizationCode(),
            'card_type'          => $response->getCardType(),
            'card_number'        => $response->getCardNumber(),
            'transbank_response' => json_encode($response),
            'status'             => $response->isApproved() ? Inscription::STATUS_COMPLETED : Inscription::STATUS_FAILED,
        ]);
        do_action('transbank_oneclick_inscription_finished', $order, $from);

        // Todo: guardar la información del usuario al momento de crear la inscripción y luego obtenerla en base al token,
        // por si se pierde la sesión
        $userInfo = wp_get_current_user();
        if (!$userInfo) {
            $this->logger->logError('You were logged out');
        }

        $this->logger->logInfo('[ONECLICK] Resultado obtenido correctamente: '.print_r($response, true));
        if ($response->isApproved()) {
            wc_add_notice(__('La tarjeta ha sido inscrita satisfactoriamente. Aún no se realiza ningún cobro. Ahora puedes realizar el pago.', 'transbank_wc_plugin'), 'success');
            $this->logger->logInfo('[ONECLICK] Inscripción aprobada');
            $token = new WC_Payment_Token_Oneclick();
            $token->set_token($response->getTbkUser()); // Token comes from payment processor
            $token->set_gateway_id($this->gatewayId);
            $token->set_last4(substr($response->getCardNumber(), -4));
            $token->set_email($inscription->email);
            $token->set_username($inscription->username);
            $token->set_card_type($response->getCardType());
            $token->set_user_id($inscription->user_id);
            $token->set_environment($inscription->environment);
            // Save the new token to the database
            $token->save();

            if ($order) {
                $order->add_order_note('Tarjeta inscrita satisfactoriamente');
            }

            Inscription::update($inscription->id, [
                'token_id' => $token->get_id(),
            ]);

            // Set this token as the users new default token
            WC_Payment_Tokens::set_users_default(get_current_user_id(), $token->get_id());

            do_action('transbank_oneclick_inscription_approved', $response, $token, $from);

            $this->logger->logError('Inscription finished successfully for user #'.$inscription->user_id);
        } else {
            //Todo: In case that the inscription fails, we need to redirect the user somewhere.
            wc_add_notice('La inscripción de la tarjeta ha sido rechazada ( '.$response->getResponseCode().' ). Puede intentar nuevamente. ', 'error');
            if ($order) {
                $order->add_order_note('La inscripción de la tarjeta ha sido rechazada (código de respuesta: '.$response->getResponseCode().')');
            }

            do_action('transbank_oneclick_inscription_failed', $response, $from);
            $this->logger->logInfo('[ONECLICK] Inscripción fallida');
        }

        $this->redirectUser($from);
    }

    /**
     * @return bool
     */
    private function transactionWasTimeout()
    {
        $buyOrder = $_POST['TBK_ORDEN_COMPRA'] ?? $_GET['TBK_ORDEN_COMPRA'] ?? null;
        $sessionId = $_POST['TBK_ID_SESION'] ?? $_GET['TBK_ID_SESION'] ?? null;
        $token = $_POST['TBK_TOKEN'] ?? $_GET['TBK_TOKEN'] ?? null;

        return $buyOrder && $sessionId && !$token;
    }

    /**
     * @param $from
     */
    public function redirectUser($from)
    {
        $redirectUrl = null;
        if ($from === 'checkout') {
            $checkout_page_id = wc_get_page_id('checkout');
            $redirectUrl = $checkout_page_id ? get_permalink($checkout_page_id) : null;
        }
        if ($from === 'my_account') {
            $redirectUrl = get_permalink(get_option('woocommerce_myaccount_page_id')).'/'.get_option(
                'woocommerce_myaccount_payment_methods_endpoint',
                'payment-methods'
            );
        }
        if ($redirectUrl) {
            wp_redirect($redirectUrl);
        }

        exit();
    }
}
