<?php

namespace Transbank\WooCommerce\WebpayRest\Models;

use Transbank\Plugin\Exceptions\TokenNotFoundOnDatabaseException;

class Transaction extends BaseModel
{
    const TRANSACTIONS_TABLE_NAME = 'webpay_rest_transactions';

    const STATUS_PREPARED = 'prepared';
    const STATUS_INITIALIZED = 'initialized';
    const STATUS_FAILED = 'failed';
    const STATUS_ABORTED_BY_USER = 'aborted_by_user';
    const STATUS_APPROVED = 'approved';

    const PRODUCT_WEBPAY_PLUS = 'webpay_plus';
    const PRODUCT_WEBPAY_ONECLICK = 'webpay_oneclick';

    /**
     * @return string
     */
    public static function getTableName(): string
    {
        return static::getBaseTableName(static::TRANSACTIONS_TABLE_NAME);
    }

    public static function createTransaction(array $data)
    {
        return static::insertBase(static::getTableName(), $data);
    }

    public static function update($transactionId, array $data)
    {
        return static::updateBase(static::getTableName(), $transactionId, $data);
    }

    /**
     * @throws TokenNotFoundOnDatabaseException
     */
    public static function getByToken($token)
    {
        global $wpdb;
        $transactionTable = static::getTableName();
        $sql = $wpdb->prepare("SELECT * FROM $transactionTable WHERE `token` = '%s'", $token);
        $sqlResult = $wpdb->get_results($sql);
        if (!is_array($sqlResult) || count($sqlResult) <= 0) {
            throw new TokenNotFoundOnDatabaseException("Token '{$token}' no se encontró en la base de datos de transacciones, por lo que no se puede completar el proceso");
        }

        return $sqlResult[0];
    }

    public static function getApprovedByOrderId($orderId)
    {
        global $wpdb;
        $transaction = static::getTableName();
        $statusApproved = static::STATUS_APPROVED;
        $sql = $wpdb->prepare(
            "SELECT * FROM $transaction WHERE status = '$statusApproved' AND order_id = '%s'",
            $orderId
        );
        $sqlResult = $wpdb->get_results($sql);

        return $sqlResult[0] ?? null;
    }

    /**
     * @throws TokenNotFoundOnDatabaseException
     */
    public static function getByBuyOrderAndSessionId($buyOrder, $sessionId)
    {
        global $wpdb;
        $transactionTableName = Transaction::getTableName();
        $sql = $wpdb->prepare(
            "SELECT * FROM $transactionTableName WHERE session_id = '%s' && buy_order='%s'",
            $sessionId,
            $buyOrder
        );
        $sqlResult = $wpdb->get_results($sql);
        if (!is_array($sqlResult) || count($sqlResult) <= 0) {
            throw new TokenNotFoundOnDatabaseException('No se encontró el session_id y buy_order en la base de datos de transacciones, por lo que no se puede completar el proceso');
        }

        return $sqlResult[0];
    }

    public static function getByBuyOrder($buyOrder)
    {
        global $wpdb;
        $transactionTableName = Transaction::getTableName();
        $sql = $wpdb->prepare(
            "SELECT * FROM $transactionTableName WHERE buy_order = '%s'",
            $buyOrder
        );
        $sqlResult = $wpdb->get_results($sql);
        if (!is_array($sqlResult) || count($sqlResult) <= 0) {
            throw new TokenNotFoundOnDatabaseException('No se encontró el session_id y order_id en la base de datos de transacciones, por lo que no se puede completar el proceso');
        }
        return $sqlResult[0];
    }

    public static function checkExistTable()
    {
        return static::checkExistTableBase(static::getTableName());
    }
}
