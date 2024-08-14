<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use DateTime;
use DateTimeZone;
use Transbank\Plugin\Helpers\PluginLogger;
use Transbank\WooCommerce\WebpayRest\WebpayplusTransbankSdk;
use Transbank\WooCommerce\WebpayRest\Helpers\TransactionResponseHandler;
use Transbank\Webpay\WebpayPlus\Responses\TransactionCommitResponse;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\Helpers\HposHelper;
use Transbank\WooCommerce\WebpayRest\Helpers\BlocksHelper;
use Transbank\Plugin\Exceptions\Webpay\AlreadyProcessedException;
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
     * @var PluginLogger
     * @var WebpayplusTransbankSdk
     * @var TransactionResponseHandler
     */
    protected array $pluginConfig;
    protected PluginLogger $logger;
    protected WebpayplusTransbankSdk $webpayplusTransbankSdk;
    protected TransactionResponseHandler $transactionResponseHandler;

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
        $this->transactionResponseHandler = new TransactionResponseHandler();
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Transbank\Plugin\Exceptions\TokenNotFoundOnDatabaseException
     */

    public function response($requestMethod, $params)
    {
        $this->logger->logInfo('Procesando retorno desde formulario de Webpay.');
        $this->logger->logInfo("Request: method -> $requestMethod");
        $this->logger->logInfo('Request: payload -> ' . json_encode($params));

        try {
            $transaction = $this->transactionResponseHandler->handleRequestFromTbkReturn($params);
            $wooCommerceOrder = $this->getWooCommerceOrderById($transaction->order_id);
            $commitResponse = $this->webpayplusTransbankSdk->commitTransaction($transaction->order_id, $transaction->token);
            $this->completeWooCommerceOrder($wooCommerceOrder, $commitResponse, $transaction);

            do_action('wc_transbank_webpay_plus_transaction_approved', [
                'order' => $wooCommerceOrder->get_data(),
                'transbankTransaction' => $transaction
            ]);
            $redirectUrl = $wooCommerceOrder->get_checkout_order_received_url();
        } catch (TimeoutWebpayException $e) {
            $this->throwError($e->getMessage());

            do_action('transbank_webpay_plus_timeout_on_form');
            $redirectUrl = $this->addErrorQueryParams(wc_get_checkout_url(), BlocksHelper::WEBPAY_TIMEOUT);
        } catch (UserCancelWebpayException $e) {
            $params = ['transbank_webpayplus_cancelled_order' => 1];
            $redirectUrl = add_query_arg($params, wc_get_checkout_url());
            $transaction = $e->getTransaction();
            $wooCommerceOrder = $this->getWooCommerceOrderById($transaction->order_id);
            $this->setOrderAsCancelledByUser($wooCommerceOrder, $transaction);

            do_action('transbank_webpay_plus_transaction_cancelled_by_user', $wooCommerceOrder, $transaction);
            $redirectUrl = $this->addErrorQueryParams($redirectUrl, BlocksHelper::WEBPAY_USER_CANCELED);
        } catch (DoubleTokenWebpayException $e) {
            $this->throwError($e->getMessage());

            do_action('transbank_webpay_plus_unexpected_error');
            $redirectUrl = $this->addErrorQueryParams(wc_get_checkout_url(), BlocksHelper::WEBPAY_DOUBLE_TOKEN);
        } catch (InvalidStatusWebpayException $e) {
            $errorMessage = 'No se puede confirmar la transacción, estado de transacción invalido.';
            $wooCommerceOrder = $this->getWooCommerceOrderById($transaction->order_id);
            $wooCommerceOrder->add_order_note($errorMessage);

            do_action('wc_transbank_webpay_plus_transaction_failed', [
                'order' => $wooCommerceOrder->get_data(),
                'transbankTransaction' => $e->getTransaction()
            ]);
            $redirectUrl = $this->addErrorQueryParams(wc_get_checkout_url(), BlocksHelper::WEBPAY_INVALID_STATUS);
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
            $redirectUrl = $wooCommerceOrder->get_checkout_order_received_url();
        } catch (CommitWebpayException $e) {
            $errorMessage = 'Error al confirmar la transacción de Transbank';
            $wooCommerceOrder = $this->getWooCommerceOrderById($transaction->order_id);
            $wooCommerceOrder->add_order_note($errorMessage);

            do_action('wc_transbank_webpay_plus_transaction_failed', [
                'order' => $wooCommerceOrder->get_data(),
                'transbankTransaction' => $e->getTransaction()
            ]);
            $redirectUrl = $this->addErrorQueryParams(wc_get_checkout_url(), BlocksHelper::WEBPAY_COMMIT_ERROR);
        } catch (AlreadyProcessedException $e) {
            $errorMessage = 'Error al confirmar la transacción, ya fue procesada anteriormente';
            $transaction = $e->getTransaction();
            $orderId = $transaction['order_id'];
            $wooCommerceOrder = $this->getWooCommerceOrderById($orderId);
            $wooCommerceOrder->add_order_note($errorMessage);

            $e->getFlow() == TransactionResponseHandler::WEBPAY_NORMAL_FLOW
                ? $redirectUrl = $wooCommerceOrder->get_checkout_order_received_url()
                : $redirectUrl = $this->addErrorQueryParams(wc_get_checkout_url(), BlocksHelper::WEBPAY_ALREADY_PROCESSED);
        } catch (\Exception $e) {
            $this->throwError($e->getMessage());
            do_action('transbank_webpay_plus_unexpected_error');
            $redirectUrl = $this->addErrorQueryParams(wc_get_checkout_url(), BlocksHelper::WEBPAY_EXCEPTION);
        }

        return wp_redirect($redirectUrl);
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

        $this->addOrderDetailsOnNotes(
            $wooCommerceOrder,
            $commitResponse,
            $message,
            $webpayTransaction->token
        );

        $maskedBuyOrder = $this->webpayplusTransbankSdk->dataMasker->maskBuyOrder($commitResponse->getBuyOrder());
        $this->logger->logInfo(
            'C.5. Transacción con commit exitoso en Transbank y guardado => OC: ' . $maskedBuyOrder
        );

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
    ) {
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

            $this->logger->logError('C.5. Respuesta de tbk commit fallido => token: ' . $webpayTransaction->token);
            $this->logger->logError(json_encode($commitResponse));
        }

        Transaction::update(
            $webpayTransaction->id,
            [
                'status' => Transaction::STATUS_FAILED,
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
    private function setAfterPaymentOrderStatus(WC_Order $order)
    {
        $status = $this->pluginConfig['STATUS_AFTER_PAYMENT'];
        if ($status == '') {
            $order->payment_complete();
        } else {
            $order->payment_complete();
            $order->update_status($status);
        }
    }

    protected function addErrorQueryParams($url, $errorCode)
    {
        $params = ['transbank_status' => $errorCode];
        return add_query_arg($params, $url);
    }
}
