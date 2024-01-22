<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use DateTime;
use DateTimeZone;
use Transbank\Webpay\WebpayPlus\Responses\TransactionCommitResponse;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\Helpers\InteractsWithFullLog;
use Transbank\WooCommerce\WebpayRest\Helpers\HposHelper;
use Transbank\WooCommerce\WebpayRest\Helpers\BlocksHelper;
use Transbank\Plugin\Exceptions\Webpay\TimeoutWebpayException;
use Transbank\Plugin\Exceptions\Webpay\UserCancelWebpayException;
use Transbank\Plugin\Exceptions\Webpay\DoubleTokenWebpayException;
use Transbank\Plugin\Exceptions\Webpay\CommitWebpayException;
use Transbank\Plugin\Exceptions\Webpay\InvalidStatusWebpayException;
use Transbank\Plugin\Exceptions\Webpay\RejectedCommitWebpayException;
use Transbank\WooCommerce\WebpayRest\WebpayplusTransbankSdk;
use WC_Order;

class ResponseController
{
    /**
     * @var array
     */
    protected $pluginConfig;

    /**
     * @var WebpayplusTransbankSdk
     */
    protected $webpayplusTransbankSdk;

    /**
     * ResponseController constructor.
     *
     * @param array $pluginConfig
     */
    public function __construct(array $pluginConfig)
    {
        $this->pluginConfig = $pluginConfig;
        $this->interactsWithFullLog = new InteractsWithFullLog();
        $this->webpayplusTransbankSdk = new WebpayplusTransbankSdk();
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
     * @throws \Transbank\Plugin\Exceptions\TokenNotFoundOnDatabaseException
     */
    public function response($postData)
    {
        try {
            $transaction = $this->webpayplusTransbankSdk->processRequestFromTbkReturn($_SERVER, $_GET, $_POST);
            $wooCommerceOrder = $this->getWooCommerceOrderById($transaction->order_id);
            if ($wooCommerceOrder->is_paid()) {
                // TODO: Revisar porqué no se muestra el mensaje de abajo. H4x
                //SessionMessageHelper::set('Orden <strong>ya ha sido pagada</strong>.', 'notice');
                $errorMessage = 'El usuario intentó pagar esta orden nuevamente, cuando esta ya estaba pagada.';
                $this->webpayplusTransbankSdk->logError($errorMessage);
                $this->webpayplusTransbankSdk->saveTransactionWithErrorByTransaction($transaction, 'transbank_webpay_plus_already_paid_transaction', $errorMessage);
                $wooCommerceOrder->add_order_note($errorMessage);
                do_action('transbank_webpay_plus_already_paid_transaction', $wooCommerceOrder);
                return wp_safe_redirect($wooCommerceOrder->get_checkout_order_received_url());
            }
            if (!$wooCommerceOrder->needs_payment()) {
                // TODO: Revisar porqué no se muestra el mensaje de abajo.
                //SessionMessageHelper::set('El estado de la orden no permite que sea pagada. Comuníquese con la tienda.', 'error');
                $errorMessage = 'El usuario intentó pagar la orden cuando estaba en estado: '.$wooCommerceOrder->get_status().".\n".'No se ejecutó captura del pago de esta solicitud.';
                $this->webpayplusTransbankSdk->logError($errorMessage);
                $this->webpayplusTransbankSdk->saveTransactionWithErrorByTransaction($transaction, 'transbank_webpay_plus_paying_transaction_that_does_not_needs_payment', $errorMessage);
                $wooCommerceOrder->add_order_note($errorMessage);
                do_action('transbank_webpay_plus_paying_transaction_that_does_not_needs_payment', $wooCommerceOrder);
                return wp_safe_redirect($wooCommerceOrder->get_checkout_order_received_url());
            }
            $commitResponse = $this->webpayplusTransbankSdk->commitTransaction($transaction->order_id, $transaction->token);
            $this->completeWooCommerceOrder($wooCommerceOrder, $commitResponse, $transaction);
            do_action('transbank_webpay_plus_transaction_approved', $wooCommerceOrder, $transaction);
            return wp_redirect($wooCommerceOrder->get_checkout_order_received_url());

        } catch (TimeoutWebpayException $e) {
            $this->throwError($e->getMessage());
            do_action('transbank_webpay_plus_timeout_on_form');
            $urlWithErrorCode = $this->addErrorQueryParams(wc_get_checkout_url(), BlocksHelper::WEBPAY_TIMEOUT);
            wp_redirect($urlWithErrorCode);
        } catch (UserCancelWebpayException $e) {
            $params = ['transbank_webpayplus_cancelled_order' => 1];
            $redirectUrl = add_query_arg($params, wc_get_checkout_url());
            $transaction = $e->getTransaction();
            $wooCommerceOrder = $this->getWooCommerceOrderById($transaction->order_id);
            if ($transaction->status !== Transaction::STATUS_INITIALIZED || $wooCommerceOrder->is_paid()) {
                $wooCommerceOrder->add_order_note('El usuario canceló la transacción en el formulario de pago, pero esta orden ya estaba pagada o en un estado diferente a INICIALIZADO');
                wp_safe_redirect($redirectUrl);
                return;
            }
            $this->setOrderAsCancelledByUser($wooCommerceOrder, $transaction);
            do_action('transbank_webpay_plus_transaction_cancelled_by_user', $wooCommerceOrder, $transaction);
            $urlWithErrorCode = $this->addErrorQueryParams($redirectUrl, BlocksHelper::WEBPAY_USER_CANCELED);
            wp_safe_redirect($urlWithErrorCode);
            return;
        } catch (DoubleTokenWebpayException $e) {
            $this->throwError($e->getMessage());
            do_action('transbank_webpay_plus_unexpected_error');
            $urlWithErrorCode = $this->addErrorQueryParams(wc_get_checkout_url(), BlocksHelper::WEBPAY_DOUBLE_TOKEN);
            wp_redirect($urlWithErrorCode);
        } catch (InvalidStatusWebpayException $e) {
            $transaction = $e->getTransaction();
            $wooCommerceOrder = $this->getWooCommerceOrderById($transaction->order_id);
            $this->setWooCommerceOrderAsFailed($wooCommerceOrder, $transaction, null, $transaction->token);
            do_action('transbank_webpay_plus_transaction_failed', $wooCommerceOrder, $transaction, null);
            return wp_redirect($wooCommerceOrder->get_checkout_order_received_url());
        } catch (RejectedCommitWebpayException $e) {
            $transaction = $e->getTransaction();
            $wooCommerceOrder = $this->getWooCommerceOrderById($transaction->order_id);
            $this->setWooCommerceOrderAsFailed($wooCommerceOrder, $transaction, $e->getCommitResponse(), $transaction->token);
            do_action('transbank_webpay_plus_transaction_failed', $wooCommerceOrder, $transaction, $e->getCommitResponse());
            return wp_redirect($wooCommerceOrder->get_checkout_order_received_url());
        } catch (CommitWebpayException $e) {
            $transaction = $e->getTransaction();
            $wooCommerceOrder = $this->getWooCommerceOrderById($transaction->order_id);
            $this->setWooCommerceOrderAsFailed($wooCommerceOrder, $transaction, null, $transaction->token);
            do_action('transbank_webpay_plus_transaction_failed', $wooCommerceOrder, $transaction, null);
            return wp_redirect($wooCommerceOrder->get_checkout_order_received_url());
        } catch (\Exception $e) {
            $this->throwError($e->getMessage());
            do_action('transbank_webpay_plus_unexpected_error');
            $urlWithErrorCode = $this->addErrorQueryParams(wc_get_checkout_url(), BlocksHelper::WEBPAY_EXCEPTION);
            wp_redirect($urlWithErrorCode);
        }
        return "";
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
        $hPosHelper = new HposHelper();

        $hPosHelper->updateMeta($wooCommerceOrder, 'transactionResponse', $transactionResponse);
        $hPosHelper->updateMeta($wooCommerceOrder, 'buyOrder', $result->buyOrder);
        $hPosHelper->updateMeta($wooCommerceOrder, 'authorizationCode', $authorizationCode);
        $hPosHelper->updateMeta($wooCommerceOrder, 'cardNumber', $cardNumber);
        $hPosHelper->updateMeta($wooCommerceOrder, 'paymentCodeResult', $paymentCodeResult);
        $hPosHelper->updateMeta($wooCommerceOrder, 'amount', $amount);
        $hPosHelper->updateMeta($wooCommerceOrder, 'installmentsNumber', $sharesNumber ? $sharesNumber : '0');
        $hPosHelper->updateMeta($wooCommerceOrder, 'installmentsAmount', $sharesAmount ? $sharesAmount : '0');
        $hPosHelper->updateMeta($wooCommerceOrder, 'transactionDate', $date);
        $hPosHelper->updateMeta($wooCommerceOrder, 'webpay_transaction_id', $webpayTransaction->id);
        $hPosHelper->updateMeta($wooCommerceOrder, 'transactionResponse', json_encode($result));

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

        $this->interactsWithFullLog->logWebpayPlusGuardandoCommitExitoso($token_ws); // Logs

        $this->setAfterPaymentOrderStatus($wooCommerceOrder);
    }

    /**
     * @param WC_Order $wooCommerceOrder
     * @param array    $result
     * @param $webpayTransaction
     */
    protected function setWooCommerceOrderAsFailed(WC_Order $wooCommerceOrder, $webpayTransaction, $result, $token)
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
        BlocksHelper::addLegacyNotices($error_message, 'error');
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

    protected function addErrorQueryParams($url, $errorCode) {
        $params = ['transbank_status' => $errorCode];
        return add_query_arg($params, $url);
    }
}
