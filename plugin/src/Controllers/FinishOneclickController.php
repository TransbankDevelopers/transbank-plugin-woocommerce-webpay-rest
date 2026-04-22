<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use \Exception;
use Throwable;
use Transbank\Plugin\Helpers\TbkConstants;
use Transbank\WooCommerce\WebpayRest\Helpers\RequestInputHelper;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Helpers\BlocksHelper;
use Transbank\WooCommerce\WebpayRest\Services\InscriptionService;
use Transbank\WooCommerce\WebpayRest\Services\OneclickInscriptionService;
use Transbank\Plugin\Helpers\PluginLogger;
use Transbank\Plugin\Exceptions\EcommerceException;
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

    const ONECLICK_NORMAL_FLOW = 'normal';
    const ONECLICK_ABORTED_FLOW = 'aborted';
    const ONECLICK_ERROR_FLOW = 'error';

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
            $this->log->logInfo('Procesando retorno desde formulario Oneclick');
            $method = RequestInputHelper::resolveRequestMethod($_SERVER);
            $rawData = $method === 'GET' ? $_GET : $_POST;
            $data = RequestInputHelper::sanitizeExpectedFields($rawData, [
                'TBK_TOKEN',
                'TBK_ID_SESION',
                'TBK_ORDEN_COMPRA',
            ]);
            $oneclickFlow = $this->getOneclickFlow($data);
            $this->log->logInfo('Resumen de retorno de Oneclick', PluginLogger::sanitizeContextForLogs([
                'method' => $method,
                'flow' => $oneclickFlow,
                'TBK_TOKEN' => $data['TBK_TOKEN'] ?? null,
                'TBK_ID_SESION' => $data['TBK_ID_SESION'] ?? null,
                'TBK_ORDEN_COMPRA' => $data['TBK_ORDEN_COMPRA'] ?? null,
            ]));
            $this->log->logInfo('Flujo de inscripción Oneclick:', [
                'flow' => $oneclickFlow
            ]);

            if ($oneclickFlow === self::ONECLICK_ABORTED_FLOW) {
                RequestInputHelper::assertValidIdentifier($data['TBK_TOKEN'], 'TBK_TOKEN');
                $this->handleAbortedFlow($data['TBK_TOKEN']);
            }
            if ($oneclickFlow === self::ONECLICK_NORMAL_FLOW) {
                RequestInputHelper::assertValidIdentifier($data['TBK_TOKEN'], 'TBK_TOKEN');
                $this->handleNormalFlow($data['TBK_TOKEN']);
            }
            if ($oneclickFlow === self::ONECLICK_ERROR_FLOW) {
                throw new EcommerceException('Parámetros inválidos recibidos desde el formulario Oneclick');
            }
        } catch (Throwable $e) {
            $this->log->logError('Error procesando el retorno de inscripción Oneclick', [
                'error' => $e->getMessage(),
            ]);
            $this->redirectUser('checkout', BlocksHelper::ONECLICK_FINISH_ERROR);
        }
    }

    /**
     * Determines the type of payment flow based on the request data.
     *
     * @param array $requestData The request data from the payment gateway.
     * @return string The type of payment flow.
     */
    protected function getOneclickFlow(array $requestData): string
    {
        $token = RequestInputHelper::hasValue($requestData["TBK_TOKEN"] ?? null);
        $tbkSessionId = RequestInputHelper::hasValue($requestData['TBK_ID_SESION'] ?? null);
        $tbkOrdenCompra = RequestInputHelper::hasValue($requestData['TBK_ORDEN_COMPRA'] ?? null);

        if ($token && !$tbkSessionId && !$tbkOrdenCompra) {
            return self::ONECLICK_NORMAL_FLOW;
        }
        if ($token && $tbkSessionId && $tbkOrdenCompra) {
            return self::ONECLICK_ABORTED_FLOW;
        }

        return self::ONECLICK_ERROR_FLOW;
    }

    /**
     * Processes the aborted inscription flow.
     *
     * @param string $token The inscription token.
     * @return void
     */
    protected function handleAbortedFlow(string $token): void
    {
        $this->log->logInfo(
            'Inscripcion abortada por el usuario desde el formulario Oneclick',
            PluginLogger::sanitizeContextForLogs(['token' => $token])
        );
        $ins = $this->inscriptionService->findByToken($token);
        if (!$ins) {
            throw new EcommerceException('No se encontró la inscripción para el token proporcionado.');
        }
        BlocksHelper::addLegacyNotices('Inscripción abortada desde el formulario. Puedes reintentar la inscripción. ', 'warning');
        $this->inscriptionService->update($ins->id, [
            'status' => TbkConstants::INSCRIPTIONS_STATUS_FAILED
        ]);
        $order = $this->ecommerceService->getOrderById($ins->order_id);
        $order->add_order_note('El usuario canceló la inscripción en el formulario de pago');

        $this->redirectUser($ins->from, BlocksHelper::ONECLICK_USER_CANCELED);
    }

    /**
     * Processes the normal inscription flow. The result of the inscription can be approved or rejected.
     *
     * @param string $token The inscription token.
     * @return void
     */
    private function handleNormalFlow(string $token)
    {
        $ins = null;

        try {
            $ins = $this->inscriptionService->getByToken($token);
            if (!$ins) {
                throw new EcommerceException('No se encontró la inscripción para el token proporcionado.');
            }
            $this->log->logInfo('Finalizando inscripción', PluginLogger::sanitizeContextForLogs([
                'userName' => $ins->username,
                'email' => $ins->email,
                'token' => $token,
            ]));
            $resp = $this->oneclickInscriptionService->finishInscription(
                $token
            );
            $this->inscriptionService->updateWithFinishResponse($ins->id, $resp);
            $order = $this->ecommerceService->getOrderById($ins->order_id);
            $from = $ins->from;
            do_action('wc_transbank_oneclick_inscription_finished', [
                'order' => $order->get_data(),
                'from' => $from
            ]);

            $userInfo = wp_get_current_user();
            if (!$userInfo) {
                throw new EcommerceException('No se encontró el usuario asociado a la inscripción');
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
        } catch (Exception $e) {
            $errorContext = [
                'token' => $token,
                'error' => $e->getMessage(),
            ];

            if ($ins) {
                $errorContext['userName'] = $ins->username;
                $errorContext['email'] = $ins->email;
            }

            $this->log->logError('Error al confirmar la inscripción', PluginLogger::sanitizeContextForLogs($errorContext));
            BlocksHelper::addLegacyNotices($e->getMessage(), 'error');
            if ($ins) {
                $this->inscriptionService->updateWithFinishResponseError($ins->id, 'error', $e->getMessage());
            }
            $this->redirectUser($ins ? $ins->from : null, BlocksHelper::ONECLICK_FINISH_ERROR);
        }
    }

    /**
     * Redirect the user to the appropriate page based on the context.
     *
     * @param string $from The context from which the user is being redirected.
     * @param string|null $errorCode Optional error code to include in the redirect.
     */
    public function redirectUser($from = null, $errorCode = null): void
    {
        $redirectUrl = wc_get_checkout_url();

        if ($from === 'my_account') {
            $redirectUrl = wc_get_endpoint_url(
                get_option('woocommerce_myaccount_payment_methods_endpoint', 'payment-methods'),
                '',
                wc_get_page_permalink('myaccount')
            );
        }

        if ($errorCode !== null) {
            $redirectUrl = add_query_arg(['transbank_status' => $errorCode], $redirectUrl);
        }

        wp_safe_redirect($redirectUrl);
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
