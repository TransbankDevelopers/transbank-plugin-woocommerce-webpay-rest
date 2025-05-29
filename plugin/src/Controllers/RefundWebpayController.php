<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Throwable;
use Transbank\Plugin\Repositories\TransactionRepositoryInterface;
use Transbank\Plugin\Services\WebpayService;
use Transbank\Plugin\Helpers\ILogger;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Services\EcommerceService;

class RefundWebpayController
{
    /**
     * @var ILogger
     */
    protected $log;
    protected TransactionRepositoryInterface $transactionRepository;
    protected WebpayService $webpayService;
    protected EcommerceService $ecommerceService;

    /**
     * Constructor initializes the logger.
     */
    public function __construct()
    {
        $this->log = TbkFactory::createLogger();
        $this->transactionRepository = TbkFactory::createTransactionRepository();
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
    public function proccess($orderId, $amount = null)
    {
        $order = null;
        $response = null;
        $webpayTransaction = null;
        try {
            $order = $this->ecommerceService->getOrderById($orderId);
            $webpayTransaction = $this->transactionRepository->findFirstApprovedByOrderId($orderId);
            if (is_null($webpayTransaction)) {
                $messageError = '<strong>Error en el reembolso:</strong><br />';
                $messageError = $messageError . 'No hay transacciones webpay para esta orden.';
                $this->log->logError($messageError);
                $order->add_order_note($messageError);
                do_action('transbank_webpay_plus_refund_transaction_not_found', $order, null, $messageError);
                return false;
            }
            $response = $this->webpayService->refund($webpayTransaction->token, round($amount));
            $this->transactionRepository->update(
            $webpayTransaction->id,
                [
                    'last_refund_type' => $response->getType(),
                    'last_refund_response' => json_encode($response)
                ]
            );
            $jsonResponse = json_encode($response, JSON_PRETTY_PRINT);
            $this->ecommerceService->addRefundOrderNote($response, $order, $amount);
            do_action('transbank_webpay_plus_refund_completed', $order, $webpayTransaction, $jsonResponse);
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
                $this->transactionRepository->update($webpayTransaction->id,[
                    'detail_error' => $messageError
                ]);
            }
            do_action('transbank_webpay_plus_refund_failed', $order, $webpayTransaction, $messageError);
        }
        return false;
    }

}


