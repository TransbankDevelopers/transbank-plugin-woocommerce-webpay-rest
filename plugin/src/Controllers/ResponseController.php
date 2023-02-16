<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use DateTime;
use DateTimeZone;
use Transbank\Webpay\WebpayPlus\Responses\TransactionCommitResponse;
use Transbank\WooCommerce\WebpayRest\Helpers\SessionMessageHelper;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\TransbankSdkWebpayRest;
use Transbank\WooCommerce\WebpayRest\Helpers\InteractsWithFullLog;
use WC_Order;

class ResponseController
{
    /**
     * @var array
     */
    protected $pluginConfig;

    /**
     * ResponseController constructor.
     *
     * @param array $pluginConfig
     */
    public function __construct(array $pluginConfig)
    {
        $this->pluginConfig = $pluginConfig;
        $this->interactsWithFullLog = new InteractsWithFullLog();
    }

    /**
     * @param $paymentTypeCode
     *
     * @return string
     */
    public static function getHumanReadableInstallemntsType($paymentTypeCode): string
    {
        $installmentTypes = [
            'VD' => 'Venta Débito',
            'VN' => 'Venta Normal',
            'VC' => 'Venta en cuotas',
            'SI' => '3 cuotas sin interés',
            'S2' => '2 cuotas sin interés',
            'NC' => 'N cuotas sin interés',
        ];
        $paymentCodeResult = isset($installmentTypes[$paymentTypeCode]) ? $installmentTypes[$paymentTypeCode] : 'Sin cuotas';

        return $paymentCodeResult;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Transbank\WooCommerce\WebpayRest\Exceptions\TokenNotFoundOnDatabaseException
     */
    public function response($postData)
    {
        if ($this->transactionWasTimeout()) {
            $this->throwError('La transacción fue cancelada automáticamente por estar inactivo mucho tiempo en el formulario de pago de Webpay. Puede reintentar el pago');
            do_action('transbank_webpay_plus_timeout_on_form');
            wp_redirect(wc_get_checkout_url());
            exit;
        }
        $token_ws = $this->getTokenWs($postData);

        $this->interactsWithFullLog->logWebpayPlusRetornandoDesdeTbk($_SERVER['REQUEST_METHOD'], $postData); // Logs

        $webpayTransaction = Transaction::getByToken($token_ws);

        $this->interactsWithFullLog->logWebpayPlusDespuesObtenerTx($token_ws, $webpayTransaction); // Logs

        $wooCommerceOrder = $this->getWooCommerceOrderById($webpayTransaction->order_id);

        if ($this->transactionWasCanceledByUser()) {
            $sessionId = $_POST['TBK_ID_SESION'] ?? $_GET['TBK_ID_SESION'] ?? null;
            $this->interactsWithFullLog->logWebpayPlusRetornandoDesdeTbkFujo3Error($sessionId); // Logs

            $params = ['transbank_webpayplus_cancelled_order' => 1];
            $redirectUrl = add_query_arg($params, wc_get_checkout_url());

            if ($webpayTransaction->status !== Transaction::STATUS_INITIALIZED || $wooCommerceOrder->is_paid()) {
                $wooCommerceOrder->add_order_note('El usuario canceló la transacción en el formulario de pago, pero esta orden ya estaba pagada o en un estado diferente a INICIALIZADO');
                wp_safe_redirect($redirectUrl);

                return;
            }
            $this->setOrderAsCancelledByUser($wooCommerceOrder, $webpayTransaction);
            do_action('transbank_webpay_plus_transaction_cancelled_by_user', $wooCommerceOrder, $webpayTransaction);
            wp_safe_redirect($redirectUrl);

            return;
        }

        if ($wooCommerceOrder->is_paid()) {
            // TODO: Revisar porqué no se muestra el mensaje de abajo. H4x
            //SessionMessageHelper::set('Orden <strong>ya ha sido pagada</strong>.', 'notice');
            $wooCommerceOrder->add_order_note('El usuario intentó pagar esta orden nuevamente, cuando esta ya '.
                'estaba pagada.');
            do_action('transbank_webpay_plus_already_paid_transaction', $wooCommerceOrder);

            $this->interactsWithFullLog->logWebpayPlusCommitTxCarroAprobadoError($token_ws, $webpayTransaction); // Logs
            
            return wp_safe_redirect($wooCommerceOrder->get_checkout_order_received_url());
        }

        if (!$wooCommerceOrder->needs_payment()) {
            // TODO: Revisar porqué no se muestra el mensaje de abajo.
            //SessionMessageHelper::set('El estado de la orden no permite que sea pagada. Comuníquese con la tienda.', 'error');
            $wooCommerceOrder->add_order_note(
                'El usuario intentó pagar la orden cuando estaba en estado: '.
                $wooCommerceOrder->get_status().".\n".
                'No se ejecutó captura del pago de esta solicitud.'
            );
            do_action('transbank_webpay_plus_paying_transaction_that_does_not_needs_payment', $wooCommerceOrder);

            return wp_safe_redirect($wooCommerceOrder->get_checkout_order_received_url());
        }

        $transbankSdkWebpay = new TransbankSdkWebpayRest($this->pluginConfig);

        $this->interactsWithFullLog->logWebpayPlusAntesCommitTx($token_ws, $webpayTransaction); // Logs

        $result = $transbankSdkWebpay->commitTransaction($token_ws);

        $this->interactsWithFullLog->logWebpayPlusDespuesCommitTx($token_ws, $result); // Logs

        if ($this->transactionIsApproved($result) && $this->validateTransactionDetails($result, $webpayTransaction)) {
            $this->completeWooCommerceOrder($wooCommerceOrder, $result, $webpayTransaction);

            do_action('transbank_webpay_plus_transaction_approved', $wooCommerceOrder, $webpayTransaction);

            $this->interactsWithFullLog->logWebpayPlusTodoOk($token_ws, $webpayTransaction); // Logs

            return wp_redirect($wooCommerceOrder->get_checkout_order_received_url());
        }

        $this->setWooCommerceOrderAsFailed($wooCommerceOrder, $webpayTransaction, $result, $token_ws);
        do_action('transbank_webpay_plus_transaction_failed', $wooCommerceOrder, $webpayTransaction, $result);

        return wp_redirect($wooCommerceOrder->get_checkout_order_received_url());
    }

    /**
     * @param $data
     *
     * @return |null
     */
    protected function getTokenWs($data)
    {
        $token_ws = isset($data['token_ws']) ? $data['token_ws'] : (isset($data['TBK_TOKEN']) ? $data['TBK_TOKEN'] : null);
        if (!isset($token_ws)) {
            $this->throwError('No se encontró el token');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        return $token_ws;
    }

    /**
     * @param $orderId
     *
     * @return WC_Order
     */
    protected function getWooCommerceOrderById($orderId)
    {
        $wooCommerceOrder = new WC_Order($orderId);

        return $wooCommerceOrder;
    }

    /**
     * @param WC_Order $wooCommerceOrder
     * @param array    $result
     * @param $webpayTransaction
     */
    protected function completeWooCommerceOrder(WC_Order $wooCommerceOrder, TransactionCommitResponse $result, $webpayTransaction)
    {
        list($authorizationCode, $amount, $sharesNumber, $transactionResponse, $paymentCodeResult, $date_accepted, $sharesAmount, $paymentType) = $this->getTransactionDetails($result);
        $cardNumber = $result->cardDetail['card_number'];
        $date = $date_accepted->format('d-m-Y / H:i:s');
        update_post_meta($wooCommerceOrder->get_id(), 'transactionResponse', $transactionResponse);
        update_post_meta($wooCommerceOrder->get_id(), 'buyOrder', $result->buyOrder);
        update_post_meta($wooCommerceOrder->get_id(), 'authorizationCode', $authorizationCode);
        update_post_meta($wooCommerceOrder->get_id(), 'cardNumber', $cardNumber);
        update_post_meta($wooCommerceOrder->get_id(), 'paymentCodeResult', $paymentCodeResult);
        update_post_meta($wooCommerceOrder->get_id(), 'amount', $amount);
        update_post_meta($wooCommerceOrder->get_id(), 'installmentsNumber', $sharesNumber ? $sharesNumber : '0');
        update_post_meta($wooCommerceOrder->get_id(), 'installmentsAmount', $sharesAmount ? $sharesAmount : '0');
        update_post_meta($wooCommerceOrder->get_id(), 'transactionDate', $date);
        update_post_meta($wooCommerceOrder->get_id(), 'webpay_transaction_id', $webpayTransaction->id);
        update_post_meta($wooCommerceOrder->get_id(), 'webpay_rest_response', json_encode($result));

        $message = 'Pago exitoso con Webpay Plus';

        $this->addOrderDetailsOnNotes(
            $amount,
            $result,
            $sharesAmount,
            $message,
            $transactionResponse,
            $authorizationCode,
            $cardNumber,
            $sharesNumber,
            $paymentType,
            $paymentCodeResult,
            $webpayTransaction,
            $date,
            $wooCommerceOrder
        );

        Transaction::update(
            $webpayTransaction->id,
            [
                'status'             => Transaction::STATUS_APPROVED,
                'transbank_status'   => $result->getStatus(),
                'transbank_response' => json_encode($result), ]
        );

        $this->interactsWithFullLog->logWebpayPlusGuardandoCommitExitoso($token_ws); // Logs

        $this->setAfterPaymentOrderStatus($wooCommerceOrder);
    }

    /**
     * @param WC_Order $wooCommerceOrder
     * @param array    $result
     * @param $webpayTransaction
     */
    protected function setWooCommerceOrderAsFailed(WC_Order $wooCommerceOrder, $webpayTransaction, $result = null, $token)
    {
        $_SESSION['woocommerce_order_failed'] = true;
        $wooCommerceOrder->update_status('failed');
        if ($result !== null) {
            $message = 'Pago rechazado';
            list($authorizationCode, $amount, $sharesNumber, $transactionResponse, $paymentCodeResult, $date_accepted, $sharesAmount, $paymentType) = $this->getTransactionDetails($result);
            $cardNumber = isset($result->cardDetail['card_number']) ? $result->cardDetail['card_number'] : '-';

            $date = $date_accepted->format('d-m-Y / H:i:s');
            $this->addOrderDetailsOnNotes(
                $amount,
                $result,
                $sharesAmount,
                $message,
                $transactionResponse,
                $authorizationCode,
                $cardNumber,
                $sharesNumber,
                $paymentType,
                $paymentCodeResult,
                $webpayTransaction,
                $date,
                $wooCommerceOrder
            );

            $this->interactsWithFullLog->logWebpayPlusCommitFallidoError($token, $result); // Logs
        }

        Transaction::update(
            $webpayTransaction->id,
            [
                'status'             => Transaction::STATUS_FAILED,
                'transbank_response' => json_encode($result),
            ]
        );
    }

    /**
     * @param array $result
     *
     * @return bool
     */
    protected function transactionIsApproved($result)
    {
        if (!isset($result->responseCode)) {
            return false;
        }

        return (int) $result->responseCode === 0;
    }

    /**
     * @param array $result
     * @param $webpayTransaction
     *
     * @return bool
     */
    protected function validateTransactionDetails($result, $webpayTransaction)
    {
        if (!isset($result->responseCode)) {
            return false;
        }

        return $result->buyOrder == $webpayTransaction->buy_order && $result->sessionId == $webpayTransaction->session_id && $result->amount == $webpayTransaction->amount;
    }

    /**
     * @param array $result
     *
     * @throws \Exception
     *
     * @return array
     */
    public function getTransactionDetails($result)
    {
        $paymentTypeCode = isset($result->paymentTypeCode) ? $result->paymentTypeCode : null;
        $authorizationCode = isset($result->authorizationCode) ? $result->authorizationCode : null;
        $amount = isset($result->amount) ? $result->amount : null;
        $sharesNumber = isset($result->installmentsNumber) ? $result->installmentsNumber : null;
        $sharesAmount = isset($result->installmentsAmount) ? $result->installmentsAmount : null;
        $responseCode = isset($result->responseCode) ? $result->responseCode : null;
        if ($responseCode === 0) {
            $transactionResponse = '¡Transacción Aprobada!';
        } else {
            $transactionResponse = 'Transacción Rechazada';
        }
        $paymentCodeResult = self::getHumanReadableInstallemntsType($paymentTypeCode);

        $paymentType = $this->getHumanReadablePaymentType($paymentTypeCode);

        $transactionDate = isset($result->transactionDate) ? $result->transactionDate : null;
        $date_accepted = new DateTime($transactionDate, new DateTimeZone('UTC'));
        $date_accepted->setTimeZone(new DateTimeZone(wc_timezone_string()));

        return [$authorizationCode, $amount, $sharesNumber, $transactionResponse, $paymentCodeResult, $date_accepted, $sharesAmount, $paymentType];
    }

    protected function setOrderAsCancelledByUser(WC_Order $order_info, $webpayTransaction)
    {
        // Transaction aborted by user
        $order_info->add_order_note(__('Webpay Plus: Pago abortado por el usuario en el formulario de pago', 'transbank_wc_plugin'));
        $order_info->update_status('cancelled');
        Transaction::update(
            $webpayTransaction->id,
            ['status' => Transaction::STATUS_ABORTED_BY_USER]
        );
    }

    /**
     * @return bool
     */
    private function transactionWasCanceledByUser()
    {
        $buyOrder = $_POST['TBK_ORDEN_COMPRA'] ?? $_GET['TBK_ORDEN_COMPRA'] ?? null;
        $sessionId = $_POST['TBK_ID_SESION'] ?? $_GET['TBK_ID_SESION'] ?? null;
        $token = $_POST['TBK_TOKEN'] ?? $_GET['TBK_TOKEN'] ?? null;

        return $buyOrder && $sessionId && $token;
    }

    /**
     * @return bool
     */
    private function transactionWasTimeout()
    {
        $buyOrder = $_POST['TBK_ORDEN_COMPRA'] ?? $_GET['TBK_ORDEN_COMPRA'] ?? null;
        $sessionId = $_POST['TBK_ID_SESION'] ?? $_GET['TBK_ID_SESION'] ?? null;
        $token = $_POST['TBK_TOKEN'] ?? $_GET['TBK_TOKEN'] ?? null;

        return $buyOrder && $sessionId && !$token;
    }

    /**
     * @param $amount
     * @param array $result
     * @param $sharesAmount
     * @param string $message
     * @param $transactionResponse
     * @param $authorizationCode
     * @param $cardNumber
     * @param $sharesNumber
     * @param $paymentType
     * @param $paymentCodeResult
     * @param $webpayTransaction
     * @param $date
     * @param WC_Order $wooCommerceOrder
     */
    protected function addOrderDetailsOnNotes(
        $amount,
        $result,
        $sharesAmount,
        string $message,
        $transactionResponse,
        $authorizationCode,
        $cardNumber,
        $sharesNumber,
        $paymentType,
        $paymentCodeResult,
        $webpayTransaction,
        $date,
        WC_Order $wooCommerceOrder
    ) {
        $amountFormatted = number_format($amount, 0, ',', '.');
        $responseCode = isset($result->responseCode) ? $result->responseCode : '-';
        $sharesAmount = $sharesAmount ? $sharesAmount : '-';
        $transactionDetails = "
            <div class='transbank_response_note'>
                <p><h3>{$message}</h3></p>

                <strong>Estado: </strong>{$transactionResponse} <br />
                <strong>Orden de compra: </strong>{$result->buyOrder} <br />
                <strong>Código de autorización: </strong>{$authorizationCode} <br />
                <strong>Últimos dígitos tarjeta: </strong>{$cardNumber} <br />
                <strong>Monto: </strong>$ {$amountFormatted} <br />
                <strong>Código de respuesta: </strong>{$responseCode} <br />
                <strong>Tipo de pago: </strong>{$paymentType} <br />
                <strong>Tipo de cuota: </strong>{$paymentCodeResult} <br />
                <strong>Número de cuotas: </strong>{$sharesNumber} <br />
                <strong>Monto de cada cuota: </strong>{$sharesAmount} <br />
                <strong>Token:</strong> {$webpayTransaction->token} <br />
                <strong>Fecha:</strong> {$date} <br />
                <strong>ID interno: </strong>{$webpayTransaction->id} <br />
            </div>
        ";
        $wooCommerceOrder->add_order_note($transactionDetails);
    }

    /**
     * @param $paymentTypeCode
     *
     * @return string|void
     */
    public static function getHumanReadablePaymentType($paymentTypeCode)
    {
        if ($paymentTypeCode != null) {
            $paymentType = __('Crédito', 'transbank_wc_plugin');
        } else {
            $paymentType = '';
        }
        if ($paymentTypeCode == 'VD') {
            $paymentType = __('Débito', 'transbank_wc_plugin');
        } elseif ($paymentTypeCode == 'VP') {
            $paymentType = __('Prepago', 'transbank_wc_plugin');
        }

        return $paymentType;
    }

    /**
     * @param string $msg
     */
    protected function throwError(string $msg)
    {
        $error_message = __($msg);
        wc_add_notice($error_message, 'error');
    }

    /**
     * @param WC_Order $order
     */
    private function setAfterPaymentOrderStatus(WC_Order $order){
        $status = $this->pluginConfig['STATUS_AFTER_PAYMENT'];
        if ($status == ''){
            $order->payment_complete();
        }
        else{
            $order->payment_complete();
            $order->update_status($status);
        }
    }
}
