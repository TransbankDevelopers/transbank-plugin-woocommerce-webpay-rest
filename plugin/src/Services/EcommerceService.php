<?php

namespace Transbank\WooCommerce\WebpayRest\Services;

use DateTime;
use DateTimeZone;
use WC_Order;
use Transbank\WooCommerce\WebpayRest\Helpers\HposHelper;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkResponseUtil;
use Transbank\Webpay\WebpayPlus\Responses\TransactionCommitResponse;
use Transbank\Plugin\Model\WebpayplusConfig;
use Transbank\Plugin\Model\OneclickConfig;

class EcommerceService
{

    /**
     * @var WebpayplusConfig
     */
    protected $webpayConfig;
    /**
     * @var OneclickConfig
     */
    protected $oneclickConfig;

    public function __construct(
            $webpayConfig,
            $oneclickConfig
        )
    {
        $this->webpayConfig = $webpayConfig;
        $this->oneclickConfig = $oneclickConfig;
    }

    /**
     * @param $orderId
     *
     * @return WC_Order
     */
    public function getOrderById($orderId)
    {
        return new WC_Order($orderId);
    }

    /**
     * @param WC_Order $order
     */
    public function setAfterPaymentOrderStatus(WC_Order $order, $status): void
    {
        $order->payment_complete();
        if (!empty($status)) {
            $order->update_status($status);
        }
    }

    /**
     * @param WC_Order $wooCommerceOrder
     * @param TransactionCommitResponse $commitResponse
     * @param string $titleMessage
     * @param string $tbkToken
     * @return void
     */
    public function addNotes(
        WC_Order $wooCommerceOrder,
        TransactionCommitResponse $commitResponse,
        string $titleMessage,
        string $tbkToken
    ) {
        $formattedAmount = TbkResponseUtil::getAmountFormatted($commitResponse->getAmount());
        $status = TbkResponseUtil::getStatus($commitResponse->getStatus());
        $paymentType = TbkResponseUtil::getPaymentType($commitResponse->getPaymentTypeCode());
        $installmentType = TbkResponseUtil::getInstallmentType($commitResponse->getPaymentTypeCode());
        $formattedAccountingDate = TbkResponseUtil::getAccountingDate($commitResponse->getAccountingDate());
        $formattedDate = TbkResponseUtil::transactionDateToLocalDate($commitResponse->getTransactionDate());
        $installmentAmount = $commitResponse->getInstallmentsAmount() ?? 0;
        $formattedInstallmentAmount = TbkResponseUtil::getAmountFormatted($installmentAmount);

        $transactionDetails = "
            <div class='transbank_response_note'>
                <p><h3>{$titleMessage}</h3></p>

                <strong>Estado: </strong>{$status} <br />
                <strong>Orden de compra: </strong>{$commitResponse->getBuyOrder()} <br />
                <strong>Código de autorización: </strong>{$commitResponse->getAuthorizationCode()} <br />
                <strong>Últimos dígitos tarjeta: </strong>{$commitResponse->getCardNumber()} <br />
                <strong>Monto: </strong>{$formattedAmount} <br />
                <strong>Código de respuesta: </strong>{$commitResponse->getResponseCode()} <br />
                <strong>Tipo de pago: </strong>{$paymentType} <br />
                <strong>Tipo de cuota: </strong>{$installmentType} <br />
                <strong>Número de cuotas: </strong>{$commitResponse->getInstallmentsNumber()} <br />
                <strong>Monto de cada cuota: </strong>{$formattedInstallmentAmount} <br />
                <strong>Fecha:</strong> {$formattedDate} <br />
                <strong>Fecha contable:</strong> {$formattedAccountingDate} <br />
                <strong>Token:</strong> {$tbkToken} <br />
            </div>
        ";
        $wooCommerceOrder->add_order_note($transactionDetails);
    }


    /**
     * @param WC_Order $wooCommerceOrder
     * @param array    $result
     * @param $webpayTransaction
     */
    public function completeWebpayOrder(
        WC_Order $wooCommerceOrder,
        TransactionCommitResponse $commitResponse,
        $webpayTransaction
    ) {
        $status = TbkResponseUtil::getStatus($commitResponse->getStatus());
        $paymentType = TbkResponseUtil::getPaymentType($commitResponse->getPaymentTypeCode());
        $date_accepted = new DateTime($commitResponse->getTransactionDate(), new DateTimeZone('UTC'));
        $date_accepted->setTimeZone(new DateTimeZone(wc_timezone_string()));
        $date = $date_accepted->format('d-m-Y H:i:s P');

        $hPosHelper = new HposHelper();
        $hPosHelper->updateMeta($wooCommerceOrder, 'transactionStatus', $status);
        $hPosHelper->updateMeta($wooCommerceOrder, 'buyOrder', $commitResponse->buyOrder);
        $hPosHelper->updateMeta($wooCommerceOrder, 'authorizationCode', $commitResponse->getAuthorizationCode());
        $hPosHelper->updateMeta($wooCommerceOrder, 'cardNumber', $commitResponse->getCardNumber());
        $hPosHelper->updateMeta($wooCommerceOrder, 'paymentType', $paymentType);
        $hPosHelper->updateMeta($wooCommerceOrder, 'amount', $commitResponse->getAmount());
        $hPosHelper->updateMeta($wooCommerceOrder, 'installmentsNumber', $commitResponse->getInstallmentsNumber());
        $hPosHelper->updateMeta($wooCommerceOrder, 'installmentsAmount', $commitResponse->getInstallmentsAmount());
        $hPosHelper->updateMeta($wooCommerceOrder, 'transactionDate', $date);
        $hPosHelper->updateMeta($wooCommerceOrder, 'webpay_transaction_id', $webpayTransaction->id);
        $hPosHelper->updateMeta($wooCommerceOrder, 'transactionResponse', json_encode($commitResponse));

        $message = 'Webpay Plus: Pago exitoso';

        $this->addNotes(
            $wooCommerceOrder,
            $commitResponse,
            $message,
            $webpayTransaction->token
        );

        $this->setAfterPaymentOrderStatus($wooCommerceOrder, $this->webpayConfig->getStatusAfterPayment());
    }


     /**
     * @param WC_Order $wooCommerceOrder
     * @param $webpayTransaction
     * @param $commitResponse
     */
    public function setWebpayOrderAsFailed(
        WC_Order $wooCommerceOrder,
        $webpayTransaction,
        TransactionCommitResponse $commitResponse
    ) {
        $_SESSION['woocommerce_order_failed'] = true;
        $wooCommerceOrder->update_status('failed');
        if ($commitResponse !== null) {
            $message = 'Webpay Plus: Pago rechazado';

            $this->addNotes(
                $wooCommerceOrder,
                $commitResponse,
                $message,
                $webpayTransaction->token
            );
        }
    }

    /**
     * @param $response
     * @param WC_Order $order
     * @param $amount
     */
    public function addRefundOrderNote($response, WC_Order $order, $amount)
    {
        $type = $response->getType() === 'REVERSED' ? 'Reversa' : 'Anulación';
        $amountFormatted = '$'.number_format($amount, 0, ',', '.');
        $commonFields = "<div class='transbank_response_note'>
            <h3>Reembolso exitoso</h3>
            <strong>Tipo:</strong> {$type}
            <strong>Monto reembolso:</strong> {$amountFormatted}";

        if($type === 'Reversa') {
            $note = "{$commonFields}
            </div>";
        }
        else {
            $balanceFormatted = '$'.number_format($response->getBalance(), 0, ',', '.');
            $transactionDate = $response->getAuthorizationDate();
            $formattedDate = TbkResponseUtil::transactionDateToLocalDate($transactionDate);

            $note = "{$commonFields}
                <strong>Saldo:</strong> {$balanceFormatted}
                <strong>Fecha:</strong> {$formattedDate}
                <strong>Código autorización:</strong> {$response->getAuthorizationCode()}
                <strong>Código de respuesta:</strong> {$response->getResponseCode()}
            </div>";
        }

        $order->add_order_note($note);
    }

    /**
     * Marks the given order as complete and updates its status if specified.
     *
     * This method sets the order's payment status to complete and then updates
     * the order status based on the configured option 'oneclick_after_payment_order_status'.
     * If no status is specified, the order is marked as complete without changing the status.
     *
     * @param WC_Order $order The order object to update.
     */
    public function completeOneclickOrder(WC_Order $order)
    {
        $status = $this->oneclickConfig->getStatusAfterPayment();
        $this->setAfterPaymentOrderStatus($order, $status);
    }

            /**
     * Sets the given order as failed and adds a note to the order.
     *
     * This method updates the status of the provided WC_Order object to 'failed' and adds a custom note to the order.
     *
     * @param WC_Order $order The order object to update.
     * @param string $orderNotes The custom note to add to the order.
     */
    public function setOneclickOrderAsFailed(WC_Order $order, string $orderNotes)
    {
        $order->update_status('failed');
        $order->add_order_note($orderNotes);
    }

    /**
     * Retrieves the total amount from an order as an integer.
     *
     * This method takes a WC_Order object, gets its total amount, formats it to remove any decimal places,
     * and then converts it to an integer.
     *
     * @param WC_Order $order The order object from which to retrieve the total amount.
     * @return int The total amount of the order as an integer.
     */
    public function getTotalAmountFromOrder(WC_Order $order): int
    {
        return (int) number_format($order->get_total(), 0, ',', '');
    }

}
