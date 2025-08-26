<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Throwable;
use Transbank\WooCommerce\WebpayRest\Services\TransactionService;
use Transbank\WooCommerce\WebpayRest\Services\OneclickService;
use Transbank\Plugin\Helpers\ILogger;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Services\EcommerceService;

class RefundOneclickController
{
    protected ILogger $log;
    protected TransactionService $transactionService;
    protected OneclickService $oneclickService;
    protected EcommerceService $ecommerceService;

    public function __construct()
    {
        $this->log = TbkFactory::createLogger();
        $this->transactionService = TbkFactory::createTransactionService();
        $this->oneclickService = TbkFactory::createOneclickService();
        $this->ecommerceService = TbkFactory::createEcommerceService();
    }
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
                do_action('transbank_oneclick_refund_transaction_not_found', $order, null, $messageError);
                return false;
            }
            $response = $this->oneclickService->refund(
                $webpayTransaction->buy_order,
                $webpayTransaction->child_commerce_code,
                $webpayTransaction->child_buy_order,
                round($amount));
            $this->transactionService->updateWithRefundResponse($webpayTransaction->id,$response);
            $this->ecommerceService->addRefundOrderNote($response, $order, $amount);
            $jsonResponse = json_encode($response, JSON_PRETTY_PRINT);
            do_action('transbank_oneclick_refund_completed', $order, $webpayTransaction, $jsonResponse);
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
            do_action('transbank_oneclick_refund_failed', $order, $webpayTransaction, $message);
            return false;
        }
    }
}
