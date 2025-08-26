<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Throwable;
use Transbank\WooCommerce\WebpayRest\Services\WebpayService;
use Transbank\Plugin\Helpers\ILogger;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Services\EcommerceService;
use Transbank\WooCommerce\WebpayRest\Services\TransactionService;

class RefundWebpayController
{
    /**
     * @var ILogger
     */
    protected $log;
    protected TransactionService $transactionService;
    protected WebpayService $webpayService;
    protected EcommerceService $ecommerceService;

    public function __construct()
    {
        $this->log = TbkFactory::createLogger();
        $this->transactionService = TbkFactory::createTransactionService();
        $this->webpayService = TbkFactory::createWebpayService();
        $this->ecommerceService = TbkFactory::createEcommerceService();
    }


    /**
     * Process refund.
     *
     * If the gateway declares 'refunds' support, this will allow it to refund.
     * a passed in amount.
     *
     * @param  int        $orderId Order ID.
     * @param  float|null $amount Refund amount.
     * @param  string     $reason Refund reason.
     * @return boolean True or false based on success, or a WP_Error object.
     */
    public function process($orderId, $amount = null, $reason = '')
    {
        $order = null;
        $response = null;
        $webpayTransaction = null;
        $this->log->logInfo("Iniciando proceso de reembolso para la orden #{$orderId}."
            . ($amount !== null ? " Monto solicitado: {$amount}." : " Monto no especificado.")
            . (!empty($reason) ? " Motivo: {$reason}." : " Motivo no especificado."));
        try {
            $order = $this->ecommerceService->getOrderById($orderId);
            $webpayTransaction = $this->transactionService->findFirstApprovedByOrderId($orderId);
            if (is_null($webpayTransaction)) {
                $messageError = '<strong>Error en el reembolso:</strong><br />';
                $messageError = $messageError . 'No hay transacciones webpay para esta orden.';
                $this->log->logError($messageError);
                $order->add_order_note($messageError);
                do_action('transbank_webpay_plus_refund_transaction_not_found', $order, null, $messageError);
                return false;
            }
            $response = $this->webpayService->refund($webpayTransaction->token, round($amount));
            $this->transactionService->updateWithRefundResponse($webpayTransaction->id, $response);
            $this->ecommerceService->addRefundOrderNote($response, $order, $amount);
            $jsonResponse = json_encode($response, JSON_PRETTY_PRINT);
            do_action('transbank_webpay_plus_refund_completed', $order, $webpayTransaction, $jsonResponse);
            return true;
        } catch (Throwable $e) {
            $message = "<strong>Error en el reembolso:</strong><br />{$e->getMessage()}";
            if (isset($response)) {
                $message .= "\n\n" . json_encode($response, JSON_PRETTY_PRINT);
            }
            $this->log->logError($message);
            $order->add_order_note($message);
            if ($webpayTransaction) {
                $this->transactionService->updateWithRefundResponseError($webpayTransaction->id, $message);
            }
            do_action('transbank_webpay_plus_refund_failed', $order, $webpayTransaction, $message);
            return false;
        }
    }

}


