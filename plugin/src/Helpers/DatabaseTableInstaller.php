<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\WooCommerce\WebpayRest\Helpers\TbkFactory;
use Transbank\WooCommerce\WebpayRest\Models\Inscription;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\WooCommerce\WebpayRest\Models\TransbankApiServiceLog;
use Transbank\WooCommerce\WebpayRest\Models\TransbankExecutionErrorLog;

require_once ABSPATH.'wp-admin/includes/upgrade.php';

class DatabaseTableInstaller
{
    const TABLE_VERSION_OPTION_KEY = 'webpay_orders_table_version';
    const LATEST_TABLE_VERSION = 6;

    public static function isUpgraded(): bool
    {
        $version = (int) get_site_option(static::TABLE_VERSION_OPTION_KEY, 0);
        if ($version >= static::LATEST_TABLE_VERSION) {
            return true;
        }

        return false;
    }

    public static function install(): bool
    {
        return static::createTables();
    }

    public static function createTableTransaction(): bool
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();

        /*
        |--------------------------------------------------------------------------
        | Webpay Transactions Table
        |--------------------------------------------------------------------------
        */
        $tableName = Transaction::getTableName();
        $sql = "CREATE TABLE `{$tableName}` (
            `id`                   bigint(20) NOT NULL AUTO_INCREMENT,
            `order_id`             varchar(60) NOT NULL,
            `buy_order`            varchar(60) NOT NULL,
            `child_buy_order`      varchar(60),
            `commerce_code`        varchar(60),
            `child_commerce_code`  varchar(60),
            `amount`               bigint(20) NOT NULL,
            `token`                varchar(100),
            `transbank_status`     varchar(100),
            `session_id`           varchar(100),
            `status`               varchar(50) NOT NULL,
            `transbank_response`   LONGTEXT,
            `last_refund_type`     varchar(100),
            `last_refund_response` LONGTEXT,
            `product`              varchar(30),
            `environment`          varchar(20),
            `error`                varchar(255),
            `detail_error`         LONGTEXT,
            `created_at`           TIMESTAMP NOT NULL  DEFAULT NOW(),
            PRIMARY KEY (id)
        ) $charsetCollate";

        dbDelta($sql);
        return DatabaseTableInstaller::createTableProccessError($tableName, $wpdb->last_error);
    }

    public static function createTableInscription(): bool
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        /*
        |--------------------------------------------------------------------------
        | Oneclick inscriptions table
        |--------------------------------------------------------------------------
        */
        $tableName = Inscription::getTableName();
        $sql = "CREATE TABLE `{$tableName}` (
            `id`                    bigint(20) NOT NULL AUTO_INCREMENT,
            `token`                 varchar(100) NOT NULL,
            `username`              varchar(100),
            `email`                 varchar(50) NOT NULL,
            `user_id`               bigint(20),
            `token_id`              bigint(20),
            `order_id`              bigint(20),
            `pay_after_inscription` TINYINT(1) DEFAULT 0,
            `finished`              TINYINT(1) NOT NULL DEFAULT 0,
            `response_code`         varchar(50),
            `authorization_code`    varchar(50),
            `card_type`             varchar(50),
            `card_number`           varchar(50),
            `from`                  varchar(50),
            `status`                varchar(50) NOT NULL,
            `environment`           varchar(20),
            `commerce_code`         varchar(60),
            `transbank_response`    LONGTEXT,
            `error`                 varchar(255),
            `detail_error`          LONGTEXT,
            `created_at`            TIMESTAMP NOT NULL  DEFAULT NOW(),
            PRIMARY KEY (id)
        ) $charsetCollate";

        dbDelta($sql);
        return DatabaseTableInstaller::createTableProccessError($tableName, $wpdb->last_error);
    }

    public static function createTableTransbankExecutionErrorLog(): bool
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        $tableName = TransbankExecutionErrorLog::getTableName();

        $sql = "CREATE TABLE `{$tableName}` (
            `id`               bigint(20) NOT NULL AUTO_INCREMENT,
            `order_id`         varchar(60) NOT NULL,
            `service`          varchar(100) NOT NULL,
            `product`          varchar(30) NOT NULL,
            `enviroment`       varchar(20) NOT NULL,
            `commerce_code`    varchar(60) NOT NULL,
            `data`             LONGTEXT,
            `error`            varchar(255),
            `original_error`   LONGTEXT,
            `custom_error`     LONGTEXT,
            `created_at`       TIMESTAMP NOT NULL  DEFAULT NOW(),
            PRIMARY KEY (id)
        ) $charsetCollate";

        dbDelta($sql);
        return DatabaseTableInstaller::createTableProccessError($tableName, $wpdb->last_error);
    }

    public static function createTableTransbankApiServiceLog(): bool
    {
        global $wpdb;
        
        $charsetCollate = $wpdb->get_charset_collate();
        $tableName = TransbankApiServiceLog::getTableName();

        $sql = "CREATE TABLE `{$tableName}` (
            `id`               bigint(20) NOT NULL AUTO_INCREMENT,
            `order_id`         varchar(60) NOT NULL,
            `service`          varchar(100) NOT NULL,
            `product`          varchar(30) NOT NULL,
            `enviroment`       varchar(20) NOT NULL,
            `commerce_code`    varchar(60) NOT NULL,
            `input`            LONGTEXT,
            `response`         LONGTEXT,
            `error`            varchar(255),
            `original_error`   LONGTEXT,
            `custom_error`     LONGTEXT,
            `created_at`       TIMESTAMP NOT NULL  DEFAULT NOW(),
            PRIMARY KEY (id)
        ) $charsetCollate";

        dbDelta($sql);
        return DatabaseTableInstaller::createTableProccessError($tableName, $wpdb->last_error);
    }

    public static function createTableProccessError($tableName, $wpdbError): bool
    {
        $success = empty($wpdbError);
        if (!$success) {
            $log = TbkFactory::createLogger();
            $log->logError('Error creating transbank tables: '.$tableName);
            $log->logError($wpdbError);

            add_settings_error($tableName.'_table_error', '', 'Transbank Webpay: Error creando tabla '.$tableName.': '.$wpdbError, 'error');
            settings_errors($tableName.'_table_error');
        }
        return $success;
    }

    public static function createTables(): bool
    {
        $successTransaction = static::createTableTransaction();
        $successInscription = static::createTableInscription();
        $successExecutionErrorLog = static::createTableTransbankExecutionErrorLog();
        $successTransbankApiServiceLog = static::createTableTransbankApiServiceLog();
        if ($successTransaction && $successInscription && $successExecutionErrorLog && $successTransbankApiServiceLog) {
            update_site_option(static::TABLE_VERSION_OPTION_KEY, static::LATEST_TABLE_VERSION);
        }
        return $successTransaction && $successInscription && $successExecutionErrorLog && $successTransbankApiServiceLog;
    }

    public static function deleteTable()
    {
        static::deleteTableByName(Transaction::getTableName());
        static::deleteTableByName(Inscription::getTableName());
        static::deleteTableByName(TransbankApiServiceLog::getTableName());
        static::deleteTableByName(TransbankExecutionErrorLog::getTableName());
    }

    public static function deleteTableByName($tableName)
    {
        global $wpdb;
        $sql = "DROP TABLE IF EXISTS `$tableName`";
        $wpdb->query($sql);
        delete_option(static::TABLE_VERSION_OPTION_KEY);
    }

    public static function createTableIfNeeded()
    {
        if (!static::isUpgraded()) {
            return static::install();
        }

        return null;
    }
}
