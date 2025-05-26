<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use Exception;
use Throwable;
use Transbank\Plugin\Repositories\TransactionRepositoryInterface;
use Transbank\Plugin\Services\WebpayService;
use Transbank\Plugin\Helpers\ILogger;
use Transbank\Plugin\Exceptions\EcommerceException;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Services\EcommerceService;
use WC_Order;


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
    public function proccess($orderId, $amount = null, $reason = '')
    {
        $order = null;
        try {
            $order = new WC_Order($orderId);
            $resp = $this->webpayplusTransbankSdk->refundTransaction($order->get_id(), round($amount));
            $refundResponse = $resp['refundResponse'];
            $transaction = $resp['transaction'];
            $jsonResponse = json_encode($refundResponse, JSON_PRETTY_PRINT);
            $this->ecommerceService->addRefundOrderNote($refundResponse, $order, $amount);
            do_action('transbank_webpay_plus_refund_completed', $order, $transaction, $jsonResponse);
            return true;
        } catch (GetTransactionWebpayException $e) {
            $this->processRefundError($order, $e, 'transbank_webpay_plus_refund_failed', null, null);
        } catch (NotFoundTransactionWebpayException $e) {
            $this->processRefundError($order, $e, 'transbank_webpay_plus_refund_transaction_not_found', null, null);
        } catch (RefundWebpayException $e) {
            $this->processRefundError($order, $e, 'transbank_webpay_plus_refund_failed', $e->getTransaction(), null);
        } catch (RejectedRefundWebpayException $e) {
            $this->processRefundError(
                $order,
                $e,
                'transbank_webpay_plus_refund_failed',
                $e->getTransaction(),
                $e->getRefundResponse()
            );
        } catch (Throwable $e) {
            $this->processRefundError($order, $e, 'transbank_webpay_plus_refund_failed', null, null);
        }
        return false;
    }

    private function processRefundError($order, $exception, $action, $tx, $response)
    {
        $messageError = '<strong>Error en el reembolso:</strong><br />';
        $messageError = $messageError . $exception->getMessage();
        if (isset($response)) {
            $messageError = $messageError . "\n\n" . json_encode($exception->getRefundResponse(), JSON_PRETTY_PRINT);
        }
        $order->add_order_note($messageError);
        do_action($action, $order, $tx, $exception->getMessage());
        throw new EcommerceException($messageError);
    }

}

