<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use WC_Order;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\Webpay\Oneclick\Exceptions\InscriptionStartException;
use Transbank\Webpay\Oneclick\Responses\InscriptionStartResponse;
use Transbank\WooCommerce\WebpayRest\Services\InscriptionService;
use Transbank\WooCommerce\WebpayRest\Services\OneclickInscriptionService;
use Transbank\Plugin\Helpers\PluginLogger;
use Transbank\WooCommerce\WebpayRest\Services\EcommerceService;

class StartOneclickController
{
    const WOOCOMMERCE_API_RETURN_ADD_PAYMENT = 'wc_gateway_transbank_oneclick_return_payments';
    protected PluginLogger $log;
    protected InscriptionService $inscriptionService;
    protected OneclickInscriptionService $oneclickInscriptionService;
    protected EcommerceService $ecommerceService;

    /**
     * Constructor initializes the logger.
     */
    public function __construct()
    {
        $this->inscriptionService = TbkFactory::createInscriptionService();
        $this->oneclickInscriptionService = TbkFactory::createOneclickInscriptionService();
        $this->ecommerceService = TbkFactory::createEcommerceService();
        $this->log = TbkFactory::createOneclickLogger();
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws InscriptionStartException
     */
    public function process(): bool
    {
        $response = $this->start(0, 'my_account');
        return wp_redirect($response->getRedirectUrl());
    }


    /**
     * @param int $orderId
     * @param string   $from
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws InscriptionStartException
     *
     * @return InscriptionStartResponse
     */
    private function start(
        int $orderId,
        string $from = 'checkout'
    ) {
        // The user selected Oneclick, Pay with new card and choosed to save it in their account.
        $userInfo = wp_get_current_user();
        $returnUrl = add_query_arg('wc-api', static::WOOCOMMERCE_API_RETURN_ADD_PAYMENT, home_url('/'));
        $inscription = $this->oneclickInscriptionService->prepareInscription(
            $userInfo->ID,
            $userInfo->user_email,
            $orderId,
            $from
        );
        $this->log->logInfo('Iniciando inscripción', [
            'userName' => $inscription->username,
            'email' => $inscription->email
        ]);
        $response = $this->oneclickInscriptionService->startInscription(
            $inscription->username,
            $inscription->email,
            $returnUrl
        );
        $inscription->token = $response->getToken();
        $this->inscriptionService->createAndGet($inscription);
        return $response;
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
    public function handleInscription(WC_Order $order)
    {
        $this->log->logInfo('[Oneclick] Inicio de inscripción');

        $response = $this->start($order->get_id());

        $this->log->logInfo('[Oneclick] Respuesta de inscripción: ');
        $this->log->logInfo(json_encode($response));
        $order->add_order_note('El usuario inició inscripción de nueva tarjeta. Redirigiendo a formulario OneClick.');

        do_action('transbank_oneclick_adding_card_from_order', $order);

        return [
            'result' => 'success',
            'redirect' => $response->getRedirectUrl(),
        ];
    }
}
