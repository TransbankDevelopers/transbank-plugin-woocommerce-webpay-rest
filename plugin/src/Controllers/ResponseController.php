<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use DateTime;
use Transbank\WooCommerce\WebpayRest\Helpers\SessionMessageHelper;
use Transbank\WooCommerce\WebpayRest\TransbankWebpayOrders;
use Transbank\WooCommerce\WebpayRest\TransbankSdkWebpayRest;
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
    }

    public function response($postData)
    {
        $token_ws = $this->getTokenWs($postData);
        $webpayTransaction = TransbankWebpayOrders::getByToken($token_ws);

        $wooCommerceOrder = $this->getWooCommerceOrderById($webpayTransaction->order_id);

        if ($this->transactionWasCanceledByUser()) {
            SessionMessageHelper::set('La transacción ha sido cancelada por el usuario', 'error');
            if ($webpayTransaction->status  !== TransbankWebpayOrders::STATUS_INITIALIZED || $wooCommerceOrder->is_paid()) {
                $wooCommerceOrder->add_order_note('El usuario canceló la transacción en el formulario de pago, pero esta orden ya estaba pagada o en un estado diferente a INICIALIZADO');
                return wp_safe_redirect($wooCommerceOrder->get_cancel_order_url());
            }
            $this->setOrderAsCancelledByUser($wooCommerceOrder, $webpayTransaction);
            return wp_safe_redirect($wooCommerceOrder->get_cancel_order_url());
        }

        if ($wooCommerceOrder->is_paid()) {
            // TODO: Revisar porqué no se muestra el mensaje de abajo. H4x
            //SessionMessageHelper::set('Orden <strong>ya ha sido pagada</strong>.', 'notice');
            $wooCommerceOrder->add_order_note('El usuario intentó pagar esta orden cuando ya ' .
                'estaba pagada, no se ejecutó captura del pago y este se debería reversar en ' .
                'los próximos días.');
            return wp_safe_redirect($wooCommerceOrder->get_checkout_order_received_url());
        }

        if (!$wooCommerceOrder->needs_payment()) {
            // TODO: Revisar porqué no se muestra el mensaje de abajo.
            //SessionMessageHelper::set('El estado de la orden no permite que sea pagada. Comuníquese con la tienda.', 'error');
            $wooCommerceOrder->add_order_note('El usuario intentó pagar la orden cuando estaba en estado: ' .
                $wooCommerceOrder->get_status() . ".\n" .
                'No se ejecutó captura del pago y este se debería reversar en los próximos días.'
            );
            return wp_safe_redirect($wooCommerceOrder->get_checkout_order_received_url());
        }

        $transbankSdkWebpay = new TransbankSdkWebpayRest($this->pluginConfig);
        $result = $transbankSdkWebpay->commitTransaction($token_ws);
        if ($this->transactionIsApproved($result) && $this->validateTransactionDetails($result, $webpayTransaction)) {
            $this->completeWooCommerceOrder($wooCommerceOrder, $result, $webpayTransaction);
            return wp_redirect($wooCommerceOrder->get_checkout_order_received_url());
        }

        $this->setWooCommerceOrderAsFailed($wooCommerceOrder, $webpayTransaction, $result);
        return wp_redirect($wooCommerceOrder->get_checkout_order_received_url());
    }

    /**
     * @param $data
     * @return |null
     */
    protected function getTokenWs($data)
    {
        $token_ws = isset($data["token_ws"]) ? $data["token_ws"] : (isset($data['TBK_TOKEN']) ? $data['TBK_TOKEN'] : null);
        if (!isset($token_ws)) {
            $this->throwError('No se encontró el token');
        }

        return $token_ws;
    }
    /**
     * @param $orderId
     * @return WC_Order
     */
    protected function getWooCommerceOrderById($orderId)
    {
        $wooCommerceOrder = new WC_Order($orderId);

        return $wooCommerceOrder;
    }

    protected function throwError($msg)
    {
        $error_message = "Estimado cliente, le informamos que su orden termin&oacute; de forma inesperada: <br />" . $msg;
        wc_add_notice(__('ERROR: ', 'transbank_webpay') . $error_message, 'error');
        die();
    }

    /**
     * @param WC_Order $wooCommerceOrder
     * @param array $result
     * @param $webpayTransaction
     */
    protected function completeWooCommerceOrder(WC_Order $wooCommerceOrder, $result, $webpayTransaction)
    {
        $wooCommerceOrder->add_order_note(__('Pago exitoso con Webpay Plus', 'transbank_webpay'));
        $wooCommerceOrder->add_order_note(json_encode($result, JSON_PRETTY_PRINT));
        $wooCommerceOrder->payment_complete();
        $final_status = $this->pluginConfig['STATUS_AFTER_PAYMENT'];
        if ($final_status) {
            $wooCommerceOrder->update_status($final_status);
        }
        list($authorizationCode, $amount, $sharesNumber, $transactionResponse, $paymentCodeResult, $date_accepted) = $this->getTransactionDetails($result);

        update_post_meta($wooCommerceOrder->get_id(), 'transactionResponse', $transactionResponse);
        update_post_meta($wooCommerceOrder->get_id(), 'buyOrder', $result->buyOrder);
        update_post_meta($wooCommerceOrder->get_id(), 'authorizationCode', $authorizationCode);
        update_post_meta($wooCommerceOrder->get_id(), 'cardNumber', $result->cardDetail->cardNumber);
        update_post_meta($wooCommerceOrder->get_id(), 'paymentCodeResult', $paymentCodeResult);
        update_post_meta($wooCommerceOrder->get_id(), 'amount', $amount);
        update_post_meta($wooCommerceOrder->get_id(), 'cuotas', $sharesNumber);
        update_post_meta($wooCommerceOrder->get_id(), 'transactionDate', $date_accepted->format('d-m-Y / H:i:s'));

        wc_add_notice(__('Pago recibido satisfactoriamente', 'transbank_webpay'));
        TransbankWebpayOrders::update($webpayTransaction->id,
            ['status' => TransbankWebpayOrders::STATUS_APPROVED, 'transbank_response' => json_encode($result)]);
    }
    /**
     * @param WC_Order $wooCommerceOrder
     * @param array $result
     * @param $webpayTransaction
     */
    protected function setWooCommerceOrderAsFailed(WC_Order $wooCommerceOrder, $webpayTransaction, $result = null)
    {
        $_SESSION['woocommerce_order_failed'] = true;
        $wooCommerceOrder->add_order_note(__('Pago rechazado', 'transbank_webpay'));
        $wooCommerceOrder->update_status('failed');
        if ($result !== null) {
            $wooCommerceOrder->add_order_note(json_encode($result, JSON_PRETTY_PRINT));
        }

        TransbankWebpayOrders::update($webpayTransaction->id,
            ['status' => TransbankWebpayOrders::STATUS_FAILED, 'transbank_response' => json_encode($result)]);
    }

    /**
     * @param array $result
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
     * @return array
     * @throws \Exception
     */
    protected function getTransactionDetails($result)
    {
        $detailOutput = $result->detailOutput;
        $paymentTypeCode = isset($detailOutput->paymentTypeCode) ? $detailOutput->paymentTypeCode : null;
        $authorizationCode = isset($detailOutput->authorizationCode) ? $detailOutput->authorizationCode : null;
        $amount = isset($detailOutput->amount) ? $detailOutput->amount : null;
        $sharesNumber = isset($detailOutput->sharesNumber) ? $detailOutput->sharesNumber : null;
        $responseCode = isset($detailOutput->responseCode) ? $detailOutput->responseCode : null;
        if ($responseCode == 0) {
            $transactionResponse = "Transacción Aprobada";
        } else {
            $transactionResponse = "Transacción Rechazada";
        }
        $paymentCodeResult = "Sin cuotas";
        if ($this->pluginConfig) {
            if (array_key_exists('VENTA_DESC', $this->pluginConfig)) {
                if (array_key_exists($paymentTypeCode, $this->pluginConfig['VENTA_DESC'])) {
                    $paymentCodeResult = $this->pluginConfig['VENTA_DESC'][$paymentTypeCode];
                }
            }
        }

        $transactionDate = isset($result->transactionDate) ? $result->transactionDate : null;
        $date_accepted = new DateTime($transactionDate);

        return [$authorizationCode, $amount, $sharesNumber, $transactionResponse, $paymentCodeResult, $date_accepted];
    }

    protected function setOrderAsCancelledByUser(WC_Order $order_info, $webpayTransaction)
    {
        // Transaction aborted by user
        $order_info->add_order_note(__('Pago abortado por el usuario en el fomulario de pago', 'transbank_webpay'));
        $order_info->update_status('cancelled');
        TransbankWebpayOrders::update($webpayTransaction->id,
            ['status' => TransbankWebpayOrders::STATUS_ABORTED_BY_USER]);
    }
    /**
     * @return bool
     */
    private function transactionWasCanceledByUser()
    {
        return isset($_POST['TBK_ORDEN_COMPRA']) && isset($_POST['TBK_ID_SESION']) && $_POST['TBK_TOKEN'];
    }
}
