<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Exception;
use Throwable;
use WC_Order;
use Transbank\Plugin\Services\TransactionService;
use Transbank\Plugin\Services\OneclickService;
use Transbank\Plugin\Helpers\ILogger;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Services\EcommerceService;

class RefundOneclickController
{
    protected ILogger $log;
    protected TransactionService $transactionService;
    protected OneclickService $oneclickService;
    protected EcommerceService $ecommerceService;

    /**
     * Constructor initializes the logger.
     */
    public function __construct()
    {
        $this->log = TbkFactory::createLogger();
        $this->transactionService = TbkFactory::createTransactionService();
        $this->oneclickService = TbkFactory::createOneclickService();
        $this->ecommerceService = TbkFactory::createEcommerceService();
    }

    public function proccess($orderId, $amount = null, $reason = '')
    {
        $order = null;
        $response = null;
        $webpayTransaction = null;
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
            $this->transactionService->update(
            $webpayTransaction->id,
                [
                    'last_refund_type' => $response->getType(),
                    'last_refund_response' => json_encode($response)
                ]
            );
            $jsonResponse = json_encode($response, JSON_PRETTY_PRINT);
            $this->ecommerceService->addRefundOrderNote($response, $order, $amount);
            do_action('transbank_oneclick_refund_completed', $order, $webpayTransaction, $jsonResponse);
            return true;
        } catch (Throwable $e) {
            $messageError = '<strong>Error en el reembolso:</strong><br />';
            $messageError = $messageError . $e->getMessage();
            if (isset($response)) {
                $messageError = $messageError . "\n\n" . json_encode($response, JSON_PRETTY_PRINT);
            }
            $this->log->logError($messageError);
            $order->add_order_note($messageError);
            if (!is_null($webpayTransaction)){
                $this->transactionService->update($webpayTransaction->id,[
                    'detail_error' => $messageError
                ]);
            }
            do_action('transbank_oneclick_refund_failed', $order, $webpayTransaction, $messageError);
        }
        return false;
    }


    /**
     * @param WC_Order $order
     * @param string   $message
     *
     * @throws \Exception
     */
    protected function failedRefund(WC_Order $order, string $message)
    {
        $order->add_order_note($message);

        throw new \Exception($message);
    }
    
}
