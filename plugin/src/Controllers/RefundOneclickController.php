<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Throwable;
use Transbank\WooCommerce\WebpayRest\Services\TransactionService;
use Transbank\WooCommerce\WebpayRest\Services\OneclickAuthorizationService;
use Transbank\Plugin\Helpers\PluginLogger;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Services\EcommerceService;

class RefundOneclickController
{
    protected PluginLogger $log;
    protected TransactionService $transactionService;
    protected OneclickAuthorizationService $oneclickAuthorizationService;
    protected EcommerceService $ecommerceService;

    public function __construct()
    {
        $this->transactionService = TbkFactory::createTransactionService();
        $this->oneclickAuthorizationService = TbkFactory::createOneclickAuthorizationService();
        $this->ecommerceService = TbkFactory::createEcommerceService();
        $this->log = TbkFactory::createOneclickLogger();
    }
    public function process($orderId, $amount, $reason = '')
    {
        if ($amount === null) {
            throw new \InvalidArgumentException('El monto de la devolución no puede ser nulo.');
        }
        $order = null;
        $response = null;
        $webpayTransaction = null;
        $this->log->logInfo('Iniciando proceso de reembolso', [
            'orderId' => $orderId,
            'amount' => $amount,
            'reason' => $reason ]);
        try {
            $order = $this->ecommerceService->getOrderById($orderId);
            $webpayTransaction = $this->transactionService->findFirstApprovedByOrderId($orderId);
            if (is_null($webpayTransaction)) {
                $messageError = '<strong>Error en el reembolso:</strong><br />';
                $messageError = $messageError . 'No hay transacciones webpay para esta orden.';
                $this->log->logError('Error en el reembolso, no hay transacciones para esta orden', ['orderId' => $orderId]);
                $order->add_order_note($messageError);
                do_action('transbank_oneclick_refund_transaction_not_found', $order, null, $messageError);
                return false;
            }
            $response = $this->oneclickAuthorizationService->refund(
                $webpayTransaction->buy_order,
                $webpayTransaction->child_commerce_code,
                $webpayTransaction->child_buy_order,
                round($amount)
            );
            $this->log->logInfo('Reembolso exitoso en Transbank', ['orderId' => $orderId]);
            $this->transactionService->updateWithRefundResponse($webpayTransaction->id, $response);
            $this->ecommerceService->addRefundOrderNote($response, $order, $amount);
            $this->log->logInfo('Reembolso actualizado en Woocommerce', ['orderId' => $orderId]);
            $jsonResponse = json_encode($response, JSON_PRETTY_PRINT);
            do_action('transbank_oneclick_refund_completed', $order, $webpayTransaction, $jsonResponse);
            return true;
        } catch (Throwable $e) {
            $message = "<strong>Error en el reembolso:</strong><br />{$e->getMessage()}";
            $this->log->logError('Error en el reembolso', [
                'orderId' => $orderId,
                'error' => $e->getMessage()
            ]);
            if (isset($response)) {
                $jsonResponse = json_encode($response, JSON_PRETTY_PRINT);
                $message .= "\n\n" . $response;
                $this->log->logError($jsonResponse);
            }
            $order->add_order_note($message);
            if ($webpayTransaction) {
                $this->transactionService->updateWithRefundResponseError($webpayTransaction->id, $message);
            }
            do_action('transbank_oneclick_refund_failed', $order, $webpayTransaction, $message);
            return false;
        }
    }
}
