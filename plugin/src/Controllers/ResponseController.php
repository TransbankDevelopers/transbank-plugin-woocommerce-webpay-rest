<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use DateTime;
use DateTimeZone;
use Transbank\Plugin\Helpers\PluginLogger;
use Transbank\WooCommerce\WebpayRest\WebpayplusTransbankSdk;
use Transbank\WooCommerce\WebpayRest\Helpers\TransactionResponseHandler;
use Transbank\Webpay\WebpayPlus\Responses\TransactionCommitResponse;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\Helpers\BlocksHelper;
use Transbank\Plugin\Exceptions\Webpay\AlreadyProcessedException;
use Transbank\Plugin\Exceptions\Webpay\TimeoutWebpayException;
use Transbank\Plugin\Exceptions\Webpay\UserCancelWebpayException;
use Transbank\Plugin\Exceptions\Webpay\DoubleTokenWebpayException;
use Transbank\Plugin\Exceptions\Webpay\CommitWebpayException;
use Transbank\Plugin\Exceptions\Webpay\InvalidStatusWebpayException;
use Transbank\Plugin\Exceptions\Webpay\RejectedCommitWebpayException;
use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Services\EcommerceService;
use Transbank\Plugin\Repositories\TransactionRepositoryInterface;
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
    protected TransactionRepositoryInterface $transactionRepository;
    protected EcommerceService $ecommerceService;

    /**
     * ResponseController constructor.
     *
     * @param array $pluginConfig
     */
    public function __construct(array $pluginConfig)
    {
        $this->logger = TbkFactory::createLogger();
        $this->pluginConfig = $pluginConfig;
        $this->transactionRepository = TbkFactory::createTransactionRepository();
        $this->webpayplusTransbankSdk = TbkFactory::createWebpayplusTransbankSdk();
        $this->transactionResponseHandler = new TransactionResponseHandler();
        $this->ecommerceService = TbkFactory::createEcommerceService();
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
            $wooCommerceOrder = $this->ecommerceService->getOrderById($transaction->order_id);
            $commitResponse = $this->webpayplusTransbankSdk->commitTransaction($transaction->order_id, $transaction->token);
            $this->ecommerceService->completeWebpayOrder($wooCommerceOrder, $commitResponse, $transaction);

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
            $wooCommerceOrder = $this->ecommerceService->getOrderById($transaction->order_id);
            $this->setOrderAsCancelledByUser($wooCommerceOrder, $transaction);

            do_action('transbank_webpay_plus_transaction_cancelled_by_user', $wooCommerceOrder, $transaction);
            $redirectUrl = $this->addErrorQueryParams($redirectUrl, BlocksHelper::WEBPAY_USER_CANCELED);
        } catch (DoubleTokenWebpayException $e) {
            $this->throwError($e->getMessage());

            do_action('transbank_webpay_plus_unexpected_error');
            $redirectUrl = $this->addErrorQueryParams(wc_get_checkout_url(), BlocksHelper::WEBPAY_DOUBLE_TOKEN);
        } catch (InvalidStatusWebpayException $e) {
            $errorMessage = 'No se puede confirmar la transacción, estado de transacción invalido.';
            $wooCommerceOrder = $this->ecommerceService->getOrderById($transaction->order_id);
            $wooCommerceOrder->add_order_note($errorMessage);

            do_action('wc_transbank_webpay_plus_transaction_failed', [
                'order' => $wooCommerceOrder->get_data(),
                'transbankTransaction' => $e->getTransaction()
            ]);
            $redirectUrl = $this->addErrorQueryParams(wc_get_checkout_url(), BlocksHelper::WEBPAY_INVALID_STATUS);
        } catch (RejectedCommitWebpayException $e) {
            $transaction = $e->getTransaction();
            $commitResponse = $e->getCommitResponse();
            $wooCommerceOrder = $this->ecommerceService->getOrderById($transaction->order_id);
            $this->ecommerceService->setWebpayOrderAsFailed($wooCommerceOrder, $transaction, $commitResponse);
            $this->transactionRepository->update(
            $transaction->id,
                [
                    'status' => Transaction::STATUS_FAILED,
                    'transbank_response' => json_encode($commitResponse),
                ]
            );
            do_action('wc_transbank_webpay_plus_transaction_failed', [
                'order' => $wooCommerceOrder->get_data(),
                'transbankTransaction' => $transaction,
                'transbankResponse' => $commitResponse
            ]);
            $redirectUrl = $wooCommerceOrder->get_checkout_order_received_url();
        } catch (CommitWebpayException $e) {
            $errorMessage = 'Error al confirmar la transacción de Transbank';
            $wooCommerceOrder = $this->ecommerceService->getOrderById($transaction->order_id);
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
            $wooCommerceOrder = $this->ecommerceService->getOrderById($orderId);
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

    protected function setOrderAsCancelledByUser(WC_Order $order_info, $webpayTransaction)
    {
        // Transaction aborted by user
        $order_info->add_order_note(__('Webpay Plus: Pago abortado por el usuario en el formulario de pago', 'transbank_wc_plugin'));
        $order_info->update_status('cancelled');
        $this->transactionRepository->update(
            $webpayTransaction->id,
            ['status' => Transaction::STATUS_ABORTED_BY_USER]
        );
    }



    /**
     * @param string $msg
     */
    protected function throwError(string $msg)
    {
        $error_message = __($msg);
        BlocksHelper::addLegacyNotices($error_message, 'error');
    }

    

    protected function addErrorQueryParams($url, $errorCode)
    {
        $params = ['transbank_status' => $errorCode];
        return add_query_arg($params, $url);
    }
}
