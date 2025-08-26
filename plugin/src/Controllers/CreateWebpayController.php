<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Exception;
use Throwable;
use Transbank\WooCommerce\WebpayRest\Services\WebpayService;
use Transbank\Plugin\Helpers\ILogger;
use Transbank\Plugin\Exceptions\EcommerceException;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Services\EcommerceService;
use Transbank\WooCommerce\WebpayRest\Helpers\ErrorHelper;
use Transbank\WooCommerce\WebpayRest\Helpers\BlocksHelper;
use Transbank\WooCommerce\WebpayRest\Services\TransactionService;
use WC_Order;


class CreateWebpayController
{
    /**
     * @var ILogger
     */
    protected $log;
    protected TransactionService $transactionService;
    protected WebpayService $webpayService;
    protected EcommerceService $ecommerceService;

    /**
     * Constructor initializes the logger.
     */
    public function __construct()
    {
        $this->log = TbkFactory::createLogger();
        $this->transactionService = TbkFactory::createTransactionService();
        $this->webpayService = TbkFactory::createWebpayService();
        $this->ecommerceService = TbkFactory::createEcommerceService();
    }

    public function process($gatewayId, $apiSlug, $orderId)
    {
        $errorHookName = 'wc_gateway_transbank_process_payment_error_' . $gatewayId;
        try {
            $order = $this->ecommerceService->getOrderById($orderId);
            do_action('transbank_webpay_plus_starting_transaction', $order);
            $amount = (int) number_format($order->get_total(), 0, ',', '');
            $returnUrl = add_query_arg('wc-api', $apiSlug, home_url('/'));
            $createResponse = $this->webpayService->createTransaction($order->get_id(), $amount, $returnUrl);
            $this->transactionService->create($createResponse);
            do_action('transbank_webpay_plus_transaction_started', $order, $createResponse->getToken());
            return [
                'result' => 'success',
                'redirect' => $createResponse->getUrl() . '?token_ws=' . $createResponse->getToken()
            ];
        } catch (EcommerceException $e) {
            $this->log->logError($e->getMessage());
            if (ErrorHelper::isGuzzleError($e)) {
                $errorMessage = ErrorHelper::getGuzzleError();
                do_action($errorHookName, new Exception($errorMessage), true);
                BlocksHelper::addLegacyNotices(ErrorHelper::getGuzzleError(), 'error');
            } else {
                $errorMessage = 'Ocurrió un error al intentar conectar con WebPay Plus. Por favor intenta mas tarde.';
                do_action($errorHookName, new Exception($errorMessage), true);
                BlocksHelper::addLegacyNotices($errorMessage, 'error');
            }
        } catch (Throwable $e) {
            $this->log->logError("Error al crear la transacción: " . $e->getMessage());
            throw new EcommerceException($e->getMessage(), $e);
        }
    }

}

