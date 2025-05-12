<?php

namespace Transbank\WooCommerce\WebpayRest\Models;

class Transaction extends BaseModel
{
    const TRANSACTIONS_TABLE_NAME = 'webpay_rest_transactions';

    const STATUS_PREPARED = 'prepared';
    const STATUS_INITIALIZED = 'initialized';
    const STATUS_APPROVED = 'approved';
    const STATUS_TIMEOUT = 'timeout';
    const STATUS_ABORTED_BY_USER = 'aborted_by_user';
    const STATUS_FAILED = 'failed';

    const PRODUCT_WEBPAY_PLUS = 'webpay_plus';
    const PRODUCT_WEBPAY_ONECLICK = 'webpay_oneclick';

    /**
     * @return string
     */
    public static function getTableName(): string
    {
        return static::getBaseTableName(static::TRANSACTIONS_TABLE_NAME);
    }

    public static function create(array $data)
    {
        return static::insertBase(static::getTableName(), $data);
    }

    public static function update($transactionId, array $data)
    {
        return static::updateBase(static::getTableName(), $transactionId, $data);
    }
    public static function findByToken($token)
    {
        global $wpdb;
        $transactionTable = static::getTableName();
        $sql = $wpdb->prepare("SELECT * FROM $transactionTable WHERE `token` = '%s'", $token);
        return $wpdb->get_results($sql);
    }

    public static function findApprovedByOrderId($orderId)
    {
        global $wpdb;
        $transaction = static::getTableName();
        $statusApproved = static::STATUS_APPROVED;
        $sql = $wpdb->prepare(
            "SELECT * FROM $transaction WHERE status = '$statusApproved' AND order_id = '%s'",
            $orderId
        );
        return $wpdb->get_results($sql);
    }

    public static function findByBuyOrderAndSessionId($buyOrder, $sessionId)
    {
        global $wpdb;
        $transactionTableName = Transaction::getTableName();
        $sql = $wpdb->prepare(
            "SELECT * FROM $transactionTableName WHERE session_id = '%s' && buy_order='%s'",
            $sessionId,
            $buyOrder
        );
        return $wpdb->get_results($sql);
    }

    public static function findByBuyOrder($buyOrder)
    {
        global $wpdb;
        $transactionTableName = Transaction::getTableName();
        $sql = $wpdb->prepare(
            "SELECT * FROM $transactionTableName WHERE buy_order = '%s'",
            $buyOrder
        );
        return $wpdb->get_results($sql);
    }

    public static function checkExistTable(): array
    {
        return static::checkExistTableBase(static::getTableName());
    }
}
