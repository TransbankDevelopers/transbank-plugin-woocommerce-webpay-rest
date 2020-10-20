<?php

namespace Transbank\WooCommerce\WebpayRest\Controllers;

use DateTime;
use Transbank\WooCommerce\WebpayRest\Exceptions\InvalidOrderException;
use Transbank\WooCommerce\WebpayRest\Helpers\SessionMessageHelper;
use Transbank\WooCommerce\WebpayRest\TransbankWebpayOrders;
use WC_Gateway_Transbank_Webpay_Plus_REST;
use WC_Order;

/**
 * Class ThanksPageController
 *
 * @package Transbank\WooCommerce\WebpayRest\Controllers
 */
class FinalProcessController
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

    /**
     * @throws InvalidOrderException
     * @throws \Transbank\WooCommerce\WebpayRest\Exceptions\TokenNotFoundOnDatabaseException
     */
    public function show()
    {
        $webpayTransaction = $this->getWebpayTransactionByToken();

        $order_info = new WC_Order($webpayTransaction->order_id);
        $transbank_data = new WC_Gateway_Transbank_Webpay_Plus_REST();
        if ($order_info->get_payment_method_title() != $transbank_data->title) {
            throw new \Exception('Esta transacción no fue pagada con Webpay');
        }

        // If we receive and order that is still initialized, it means it did not pass to the responseUrl, so this
        // is a 'Anular' button flow or a Webpay Error link press.
        switch ($webpayTransaction->status) {
            case TransbankWebpayOrders::STATUS_INITIALIZED:
            case TransbankWebpayOrders::STATUS_ABORTED_BY_USER:
                //wc_add_notice(,
                SessionMessageHelper::set('Compra <strong>Anulada</strong> por usuario. Recuerda que puedes volver a intentar el pago', 'error');

                $this->setOrderAsCancelledByUser($order_info, $webpayTransaction);
                return wp_redirect($order_info->get_cancel_order_url());

            case TransbankWebpayOrders::STATUS_APPROVED:
                return wp_redirect($order_info->get_checkout_order_received_url());

            case TransbankWebpayOrders::STATUS_FAILED:
                wc_add_notice('Pago <strong>fallido</strong>. Su pago no ha sido efectuado, pero puede volver a intentarlo', 'error');
                return wp_redirect($order_info->get_cancel_order_url());
        }
    }
    /**
     * @param WC_Order $order_info
     * @param $webpayTransaction
     */
    protected function setOrderAsCancelledByUser(WC_Order $order_info, $webpayTransaction)
    {
// Transaction aborted by user
        $order_info->add_order_note(__('Pago abortado por el usuario', 'transbank_webpay'));
        $order_info->update_status('cancelled');
        TransbankWebpayOrders::update($webpayTransaction->id,
            ['status' => TransbankWebpayOrders::STATUS_ABORTED_BY_USER]);
    }
    /**
     * @param $orderId
     * @return mixed
     * @throws InvalidOrderException
     * @throws \Transbank\WooCommerce\WebpayRest\Exceptions\TokenNotFoundOnDatabaseException
     */
    protected function getWebpayTransactionByToken()
    {
        $token = (isset($_POST['token_ws']) ? $_POST['token_ws'] : (issspoet($_POST['TBK_TOKEN']) ? $_POST['TBK_TOKEN'] : (isset($_GET['token_ws']) ? $_GET['token_ws'] : null)));
        $webpayTransaction = null;
        if ($token !== null) {
            $webpayTransaction = TransbankWebpayOrders::getByToken($token);
        } elseif (isset($_POST['TBK_ORDEN_COMPRA']) && isset($_POST['TBK_ID_SESION'])) {
            $webpayTransaction = TransbankWebpayOrders::getBySessionIdAndOrderId($_POST['TBK_ID_SESION'],
                $_POST['TBK_ORDEN_COMPRA']);
        } else {
            throw new \Exception('Token not provided');
        }

        if (!$webpayTransaction) {
            throw new InvalidOrderException('Invalid token');
        }

        return $webpayTransaction;
    }
}
