<?php

namespace Transbank\WooCommerce\WebpayRest;

use Transbank\WooCommerce\WebpayRest\Exceptions\TokenNotFoundOnDatabaseException;
use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;

class TransbankWebpayOrders
{
    const TRANSACTIONS_TABLE_NAME = 'webpay_rest_transactions';
    const ONECLICK_INSCRIPTIONS_TABLE_NAME = 'webpay_oneclick_inscriptions';

    const STATUS_INITIALIZED = 'initialized';
    const STATUS_FAILED = 'failed';
    const STATUS_ABORTED_BY_USER = 'aborted_by_user';
    const STATUS_APPROVED = 'approved';

    const PRODUCT_WEBPAY_PLUS = 'webpay_plus';
    const PRODUCT_WEBPAY_ONECLICK = 'webpay_oneclick';

    /**
     * @return string
     */
    public static function getWebpayTransactionsTableName(): string
    {
        global $wpdb;
        if (is_multisite()) {
            return $wpdb->base_prefix . static::TRANSACTIONS_TABLE_NAME;
        } else {
            return $wpdb->prefix . static::TRANSACTIONS_TABLE_NAME;
        }
    }

    public static function getOneclickInscriptionsTableName(): string
    {
        global $wpdb;
        if (is_multisite()) {
            return $wpdb->base_prefix . static::ONECLICK_INSCRIPTIONS_TABLE_NAME;
        } else {
            return $wpdb->prefix . static::ONECLICK_INSCRIPTIONS_TABLE_NAME;
        }
    }

    public static function createTransaction(array $data)
    {
        global $wpdb;
        return $wpdb->insert(static::getWebpayTransactionsTableName(), $data);
    }

    public static function update($transactionId, array $data)
    {
        global $wpdb;
        $transaction = TransbankWebpayOrders::getWebpayTransactionsTableName();

        return $wpdb->update($transaction, $data, ['id' => $transactionId]);
    }

    /**
     * @throws TokenNotFoundOnDatabaseException
     */
    public static function getByToken($token)
    {
        global $wpdb;
        $transaction = TransbankWebpayOrders::getWebpayTransactionsTableName();
        $sql = $wpdb->prepare("SELECT * FROM $transaction WHERE `token` = '%s'", $token);
        $sqlResult = $wpdb->get_results($sql);
        if (!is_array($sqlResult) || count($sqlResult) <= 0) {
            throw new TokenNotFoundOnDatabaseException("Token '{$token}' no se encontró en la base de datos de transacciones, por lo que no se puede completar el proceso");
        }
        return $sqlResult[0];
    }

    public static function getApprovedByOrderId($orderId)
    {
        global $wpdb;
        $transaction = static::getWebpayTransactionsTableName();
        $statusApproved = static::STATUS_APPROVED;
        $sql = $wpdb->prepare("SELECT * FROM $transaction WHERE status = '$statusApproved' AND order_id = '%s'",
            $orderId);
        $sqlResult = $wpdb->get_results($sql);

        return $sqlResult[0] ?? null;
    }

    /**
     * @throws TokenNotFoundOnDatabaseException
     */
    public static function getBySessionIdAndOrderId($TBK_ID_SESION, $TBK_ORDEN_COMPRA)
    {
        global $wpdb;
        $transactionTableName = TransbankWebpayOrders::getWebpayTransactionsTableName();
        $sql = $wpdb->prepare("SELECT * FROM $transactionTableName WHERE session_id = '%s' && order_id='%s'",
            $TBK_ID_SESION, $TBK_ORDEN_COMPRA);
        $sqlResult = $wpdb->get_results($sql);
        if (!is_array($sqlResult) || count($sqlResult) <= 0) {
            throw new TokenNotFoundOnDatabaseException('No se encontró el session_id y order_id en la base de datos de transacciones, por lo que no se puede completar el proceso');
        }

        return $sqlResult[0];
    }

}
