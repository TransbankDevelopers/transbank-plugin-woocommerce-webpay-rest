<?php

namespace Transbank\WooCommerce\WebpayRest;

use Transbank\WooCommerce\WebpayRest\Exceptions\TokenNotFoundOnDatabaseException;
use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;

class TransbankWebpayOrders
{
    const TRANSACTIONS_TABLE_NAME = 'webpay_rest_transactions';

    const STATUS_INITIALIZED = 'initialized';
    const STATUS_FAILED = 'failed';
    const STATUS_ABORTED_BY_USER = 'aborted_by_user';
    const STATUS_APPROVED = 'approved';

    const TABLE_VERSION_OPTION_KEY = 'webpay_orders_table_version';
    const LATEST_TABLE_VERSION = 2;

    /**
     * @return string
     */
    public static function getWebpayTransactionsTableName()
    {
        global $wpdb;
        if (is_multisite()) {
            return $wpdb->base_prefix.static::TRANSACTIONS_TABLE_NAME;
        } else {
            return $wpdb->prefix.static::TRANSACTIONS_TABLE_NAME;
        }
    }

    public static function createTransaction(array $data)
    {
        global $wpdb;
        $wpdb->insert(static::getWebpayTransactionsTableName(), $data);
    }

    public static function getByToken($token)
    {
        global $wpdb;
        $transaction = TransbankWebpayOrders::getWebpayTransactionsTableName();
        $sql = $wpdb->prepare("SELECT * FROM $transaction WHERE token = '%s'", $token);
        $sqlResult = $wpdb->get_results($sql);
        if (!is_array($sqlResult) || count($sqlResult) <= 0) {
            throw new TokenNotFoundOnDatabaseException("Token '{$token}' no se encontró en la base de datos de transacciones, por lo que no se puede completar el proceso");
        }
        $webpayTransaction = $sqlResult[0];

        return $webpayTransaction;
    }

    public static function getApprovedByOrderId($orderId)
    {
        global $wpdb;
        $transaction = static::getWebpayTransactionsTableName();
        $statusApproved = static::STATUS_APPROVED;
        $sql = $wpdb->prepare("SELECT * FROM $transaction WHERE status = '$statusApproved' AND order_id = '%s'", $orderId);
        $sqlResult = $wpdb->get_results($sql);

        return isset($sqlResult[0]) ? $sqlResult[0] : null;
    }

    public static function getBySessionIdAndOrderId($TBK_ID_SESION, $TBK_ORDEN_COMPRA)
    {
        global $wpdb;
        $transactionTableName = TransbankWebpayOrders::getWebpayTransactionsTableName();
        $sql = $wpdb->prepare("SELECT * FROM $transactionTableName WHERE session_id = '%s' && order_id='%s'", $TBK_ID_SESION, $TBK_ORDEN_COMPRA);
        $sqlResult = $wpdb->get_results($sql);
        if (!is_array($sqlResult) || count($sqlResult) <= 0) {
            throw new TokenNotFoundOnDatabaseException('No se encontró el session_id y order_id en la base de datos de transacciones, por lo que no se puede completar el proceso');
        }
        $webpayTransaction = $sqlResult[0];

        return $webpayTransaction;
    }

    public static function update($transactionId, array $data)
    {
        global $wpdb;
        $transaction = TransbankWebpayOrders::getWebpayTransactionsTableName();

        return $wpdb->update($transaction, $data, ['id' => $transactionId]);
    }

    public static function isUpgraded()
    {
        $version = (int) get_site_option(static::TABLE_VERSION_OPTION_KEY, 0);
        if ($version >= static::LATEST_TABLE_VERSION) {
            return true;
        }

        return false;
    }

    public static function createTableIfNeeded()
    {
        if (!static::isUpgraded()) {
            return static::createTable();
        }

        return null;
    }

    public static function createTable()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $transactionTableName = static::getWebpayTransactionsTableName();

        $sql = "CREATE TABLE `{$transactionTableName}` (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `order_id` varchar(60) NOT NULL,
            `buy_order` varchar(60) NOT NULL,
            `amount` bigint(20) NOT NULL,
            `token` varchar(100) NOT NULL,
            `session_id` varchar(100),
            `status` varchar(50) NOT NULL,
            `transbank_response` LONGTEXT,
            `created_at` TIMESTAMP NOT NULL  DEFAULT NOW(),
            PRIMARY KEY (id)
        ) $charset_collate";

        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        $success = empty($wpdb->last_error);
        if ($success) {
            update_site_option(static::TABLE_VERSION_OPTION_KEY, static::LATEST_TABLE_VERSION);
        } else {
            $log = new LogHandler();
            $log->logError('Error creating webpay_orders table');
            $log->logError($wpdb->last_error);

            add_settings_error('transbank_webpay_orders_table_error', '', 'Transbank Webpay: Error creando tabla webpay_orders: '.$wpdb->last_error, 'error');
            settings_errors('transbank_webpay_orders_table_error');
        }

        return $success;
    }

    public static function deleteTable()
    {
        global $wpdb;
        $table_name = TransbankWebpayOrders::getWebpayTransactionsTableName();
        $sql = "DROP TABLE IF EXISTS `$table_name`";
        $wpdb->query($sql);
        delete_option(static::TABLE_VERSION_OPTION_KEY);
    }
}
