<?php

namespace Transbank\WooCommerce\WebpayRest\Services;

use DateTime;
use DateTimeZone;
use WC_Order;
use Transbank\Plugin\Helpers\ILogger;
use Transbank\WooCommerce\WebpayRest\Helpers\HposHelper;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkResponseUtil;
use Transbank\Webpay\WebpayPlus\Responses\TransactionCommitResponse;
use Transbank\Plugin\Helpers\MaskData;
use Transbank\Plugin\Model\WebpayplusConfig;

class EcommerceService
{

    /**
     * @var ILogger
     */
    protected $log;
    /**
     * @var MaskData
     */
    protected $webpayDataMasker;
    /**
     * @var WebpayplusConfig
     */
    protected $webpayConfig;

    public function __construct(
            $log,
            $webpayConfig,
        )
    {
        $this->log = $log;
        $this->webpayConfig = $webpayConfig;
        $this->webpayDataMasker = new MaskData($webpayConfig->isIntegration());
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

        $maskedBuyOrder = $this->webpayDataMasker->maskBuyOrder($commitResponse->getBuyOrder());
        $this->log->logInfo(
            'Transacción con commit exitoso en Transbank y guardado => OC: ' . $maskedBuyOrder
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

            $this->log->logError('C.5. Respuesta de tbk commit fallido => token: ' . $webpayTransaction->token);
            $this->log->logError(json_encode($commitResponse));
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

}
