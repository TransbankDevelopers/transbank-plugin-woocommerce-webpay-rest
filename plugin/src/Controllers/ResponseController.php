<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use DateTime;
use DateTimeZone;
use Transbank\Webpay\WebpayPlus\Responses\TransactionCommitResponse;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\Helpers\HposHelper;
use Transbank\WooCommerce\WebpayRest\Helpers\BlocksHelper;
use Transbank\Plugin\Exceptions\Webpay\TimeoutWebpayException;
use Transbank\Plugin\Exceptions\Webpay\UserCancelWebpayException;
use Transbank\Plugin\Exceptions\Webpay\DoubleTokenWebpayException;
use Transbank\Plugin\Exceptions\Webpay\CommitWebpayException;
use Transbank\Plugin\Exceptions\Webpay\InvalidStatusWebpayException;
use Transbank\Plugin\Exceptions\Webpay\RejectedCommitWebpayException;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkResponseUtil;
use WC_Order;

class ResponseController
{
    /**
     * @var array
     */
    protected $pluginConfig;

    protected $logger;

    /**
     * @var Transbank\WooCommerce\WebpayRest\WebpayplusTransbankSdk
     */
    protected $webpayplusTransbankSdk;

    /**
     * ResponseController constructor.
     *
     * @param array $pluginConfig
     */
    public function __construct(array $pluginConfig)
    {
        $this->logger = TbkFactory::createLogger();
        $this->pluginConfig = $pluginConfig;
        $this->webpayplusTransbankSdk = TbkFactory::createWebpayplusTransbankSdk();
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
            do_action('wc_transbank_webpay_plus_transaction_approved', [
                'order' => $wooCommerceOrder->get_data(),
                'transbankTransaction' => $transaction
            ]);
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
            $errorMessage = 'No se puede confirmar la transacción, estado de transacción invalido.';
            $wooCommerceOrder = $this->getWooCommerceOrderById($transaction->order_id);
            $wooCommerceOrder->add_order_note($errorMessage);

            do_action('wc_transbank_webpay_plus_transaction_failed', [
                'order' => $wooCommerceOrder->get_data(),
                'transbankTransaction' => $e->getTransaction()
            ]);

            $urlWithErrorCode = $this->addErrorQueryParams(wc_get_checkout_url(), BlocksHelper::WEBPAY_INVALID_STATUS);
            return wp_redirect($urlWithErrorCode);
        } catch (RejectedCommitWebpayException $e) {
            $transaction = $e->getTransaction();
            $commitResponse = $e->getCommitResponse();
            $wooCommerceOrder = $this->getWooCommerceOrderById($transaction->order_id);
            $this->setWooCommerceOrderAsFailed($wooCommerceOrder, $transaction, $commitResponse);

            do_action('wc_transbank_webpay_plus_transaction_failed', [
                'order' => $wooCommerceOrder->get_data(),
                'transbankTransaction' => $transaction,
                'transbankResponse' => $commitResponse
            ]);

            return wp_redirect($wooCommerceOrder->get_checkout_order_received_url());
        } catch (CommitWebpayException $e) {
            $errorMessage = 'Error al confirmar la transacción de Transbank';
            $wooCommerceOrder = $this->getWooCommerceOrderById($transaction->order_id);
            $wooCommerceOrder->add_order_note($errorMessage);

            do_action('wc_transbank_webpay_plus_transaction_failed', [
                'order' => $wooCommerceOrder->get_data(),
                'transbankTransaction' => $e->getTransaction()
            ]);

            $urlWithErrorCode = $this->addErrorQueryParams(wc_get_checkout_url(), BlocksHelper::WEBPAY_COMMIT_ERROR);
            return wp_redirect($urlWithErrorCode);
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
    protected function completeWooCommerceOrder(
        WC_Order $wooCommerceOrder,
        TransactionCommitResponse $commitResponse,
        $webpayTransaction
    )
    {
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

        $this->addOrderDetailsOnNotes(
            $wooCommerceOrder,
            $commitResponse,
            $message,
            $webpayTransaction->token
        );

        $maskedBuyOrder = $this->webpayplusTransbankSdk->dataMasker->maskBuyOrder($commitResponse->getBuyOrder());
        $this->logger->logInfo(
            'C.5. Transacción con commit exitoso en Transbank y guardado => OC: '.$maskedBuyOrder);

        $this->setAfterPaymentOrderStatus($wooCommerceOrder);
    }

    /**
     * @param WC_Order $wooCommerceOrder
     * @param array    $result
     * @param $webpayTransaction
     */
    protected function setWooCommerceOrderAsFailed(
        WC_Order $wooCommerceOrder,
        $webpayTransaction,
        TransactionCommitResponse $commitResponse
    )
    {
        $_SESSION['woocommerce_order_failed'] = true;
        $wooCommerceOrder->update_status('failed');
        if ($commitResponse !== null) {
            $message = 'Webpay Plus: Pago rechazado';

            $this->addOrderDetailsOnNotes(
                $wooCommerceOrder,
                $commitResponse,
                $message,
                $webpayTransaction->token
            );

            $this->logger->logError('C.5. Respuesta de tbk commit fallido => token: '.$webpayTransaction->token);
            $this->logger->logError(json_encode($commitResponse));
        }

        Transaction::update(
            $webpayTransaction->id,
            [
                'status'             => Transaction::STATUS_FAILED,
                'transbank_response' => json_encode($commitResponse),
            ]
        );
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
     * @param WC_Order $wooCommerceOrder
     * @param TransactionCommitResponse $commitResponse
     * @param string $titleMessage
     * @param string $tbkToken
     * @return void
     */
    protected function addOrderDetailsOnNotes(
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
