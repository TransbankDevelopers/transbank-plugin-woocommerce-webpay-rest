<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use \Exception;
use Throwable;
use Transbank\Plugin\Helpers\TbkConstants;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Helpers\BlocksHelper;
use Transbank\WooCommerce\WebpayRest\Services\InscriptionService;
use Transbank\WooCommerce\WebpayRest\Services\OneclickInscriptionService;
use Transbank\Plugin\Helpers\PluginLogger;
use Transbank\WooCommerce\WebpayRest\Services\EcommerceService;
use Transbank\WooCommerce\WebpayRest\Tokenization\WC_Payment_Token_Oneclick;
use WC_Payment_Tokens;

class FinishOneclickController
{
    protected PluginLogger $log;
    protected InscriptionService $inscriptionService;
    protected OneclickInscriptionService $oneclickInscriptionService;
    protected EcommerceService $ecommerceService;
    protected $gatewayId;


    /**
     * OneclickInscriptionResponseController constructor.
     */
    public function __construct($gatewayId)
    {
        $this->gatewayId = $gatewayId;
        $this->inscriptionService = TbkFactory::createInscriptionService();
        $this->oneclickInscriptionService = TbkFactory::createOneclickInscriptionService();
        $this->ecommerceService = TbkFactory::createEcommerceService();
        $this->log = TbkFactory::createOneclickLogger();
    }

    public function process()
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'];

            $data = $method === 'GET' ? $_GET : $_POST;
            $token = isset($data["TBK_TOKEN"]) ? $data['TBK_TOKEN'] : null;
            $tbkSessionId = isset($data["TBK_ID_SESION"]) ? $data['TBK_ID_SESION'] : null;
            $tbkOrdenCompra = isset($data["TBK_ORDEN_COMPRA"]) ? $data['TBK_ORDEN_COMPRA'] : null;
            $this->log->logInfo('Procesando retorno desde formulario Oneclick', [
                'tbkOrdenCompra' => $tbkOrdenCompra,
                'tbkSessionId' => $tbkSessionId,
                'token' => $token
            ]);

            if ($tbkOrdenCompra && $tbkSessionId && !$token) {
                BlocksHelper::addLegacyNotices('La inscripción fue cancelada automáticamente por estar inactiva mucho tiempo.', 'error');
                $params = ['transbank_status' => BlocksHelper::ONECLICK_TIMEOUT];
                $redirectUrl = add_query_arg($params, wc_get_checkout_url());
                wp_redirect($redirectUrl);
                exit;
            }

            if (!isset($token)) {
                $params = ['transbank_status' => BlocksHelper::ONECLICK_WITHOUT_TOKEN];
                BlocksHelper::addLegacyNotices('No se recibió el token de la inscripción.', 'error');
                $redirectUrl = add_query_arg($params, wc_get_checkout_url());
                wp_safe_redirect($redirectUrl);
                exit;
            }

            $ins = $this->inscriptionService->getByToken($token);

            if (isset($tbkOrdenCompra)) {//se abandono la inscripcion al haber presionado la opción 'Abandonar y volver al comercio'
                BlocksHelper::addLegacyNotices('Inscripción abortada desde el formulario. Puedes reintentar la inscripción. ', 'warning');
                if ($ins != null) {
                    $this->inscriptionService->update($ins->id, [
                        'status' => TbkConstants::INSCRIPTIONS_STATUS_FAILED
                    ]);
                    $order = $this->ecommerceService->getOrderById($ins->order_id);
                }
                if ($order != null) {
                    $order->add_order_note('El usuario canceló la inscripción en el formulario de pago');
                    $params = ['transbank_cancelled_order' => 1, 'transbank_status' => BlocksHelper::ONECLICK_USER_CANCELED];
                    $redirectUrl = add_query_arg($params, wc_get_checkout_url());
                    wp_safe_redirect($redirectUrl);
                    exit;
                }
                $this->redirectUser($ins->from, BlocksHelper::ONECLICK_USER_CANCELED);
            }

            $this->finishInscription($ins, $token);
        } catch (Throwable $e) {
            BlocksHelper::addLegacyNotices($e->getMessage(), 'error');
            $this->redirectUser($ins->from, BlocksHelper::ONECLICK_FINISH_ERROR);
        }
    }

    private function finishInscription($ins, $token)
    {
        try {
            $this->log->logInfo('Finalizando inscripción', [
                'token' => $token,
                'userName' => $ins->username,
                'email' => $ins->email
            ]);
            $resp = $this->oneclickInscriptionService->finishInscription(
                $token,
                $ins->username,
                $ins->email
            );
        } catch (Exception $e) {
            $this->log->logError('Error al confirmar la inscripción', [
                'token' => $token,
                'userName' => $ins->username,
                'email' => $ins->email,
                'error' => $e->getMessage(),
            ]);
            BlocksHelper::addLegacyNotices($e->getMessage(), 'error');
            $this->inscriptionService->updateWithFinishResponseError($ins->id, 'error', $e->getMessage());
            $this->redirectUser($ins->from, BlocksHelper::ONECLICK_FINISH_ERROR);
        }        
        $this->inscriptionService->updateWithFinishResponse($ins->id, $resp);
        $order = $this->ecommerceService->getOrderById($ins->order_id);
        $from = $ins->from;
        do_action('wc_transbank_oneclick_inscription_finished', [
            'order' => $order->get_data(),
            'from' => $from
        ]);

        $userInfo = wp_get_current_user();
        if (!$userInfo) {
            $this->log->logError('You were logged out');
        }
        $message = 'Tarjeta inscrita satisfactoriamente. Aún no se realiza ningún cobro. Ahora puedes realizar el pago.';
        BlocksHelper::addLegacyNotices(__($message, 'transbank_wc_plugin'), 'success');
        $token = $this->savePaymentToken($ins, $resp);
        if ($order) {
            $order->add_order_note('Tarjeta inscrita satisfactoriamente');
        }

        $this->inscriptionService->update($ins->id, [
            'token_id' => $token->get_id(),
        ]);

        WC_Payment_Tokens::set_users_default(get_current_user_id(), $token->get_id());

        do_action('wc_transbank_oneclick_inscription_approved', [
            'transbankInscriptionResponse' => $resp,
            'transbankToken' => $token,
            'from' => $from
        ]);
        $this->log->logInfo('Inscripción finalizada correctamente', ['user' => $ins->user_id]);
        $this->redirectUser($from, BlocksHelper::ONECLICK_SUCCESSFULL_INSCRIPTION);
    }


    /**
     * Redirect the user to the appropriate page based on the context.
     *
     * @param string $from The context from which the user is being redirected.
     * @param string|null $errorCode Optional error code to include in the redirect.
     */
    public function redirectUser($from, $errorCode = null): void
    {
        $redirectUrl = null;
        if ($from === 'checkout') {
            $checkoutPageId = wc_get_page_id('checkout');
            $redirectUrl = $checkoutPageId ? get_permalink($checkoutPageId) : null;
        }
        if ($from === 'my_account') {
            $redirectUrl = get_permalink(get_option('woocommerce_myaccount_page_id')) . '/' . get_option(
                'woocommerce_myaccount_payment_methods_endpoint',
                'payment-methods'
            );
        }
        if ($redirectUrl) {
            if (isset($errorCode)) {
                $params = ['transbank_status' => $errorCode];
                $redirectUrl = add_query_arg($params, $redirectUrl);
            }
            wp_redirect($redirectUrl);
            exit;
        }
    }

    private function savePaymentToken($inscription, $finishInscriptionResponse)
    {
        $token = new WC_Payment_Token_Oneclick();
        $token->set_token($finishInscriptionResponse->getTbkUser()); // Token comes from payment processor
        $token->set_gateway_id($this->gatewayId);
        $token->set_last4(substr($finishInscriptionResponse->getCardNumber(), -4));
        $token->set_email($inscription->email);
        $token->set_username($inscription->username);
        $token->set_card_type($finishInscriptionResponse->getCardType());
        $token->set_user_id($inscription->user_id);
        $token->set_environment($inscription->environment);
        $token->save();
        return $token;
    }
}
