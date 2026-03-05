<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\WooCommerce\WebpayRest\Repositories\InscriptionRepository;
use Transbank\WooCommerce\WebpayRest\Repositories\TransactionRepository;
use Transbank\WooCommerce\WebpayRest\Exceptions\CreateTableException;
use Transbank\WooCommerce\WebpayRest\Infrastructure\Database\WpdbTableGateway;

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

class DatabaseTableInstaller
{
    const TABLE_VERSION_OPTION_KEY = 'webpay_orders_table_version';
    const LATEST_TABLE_VERSION = 6;

    public static function isUpgraded(): bool
    {
        $version = (int) get_site_option(static::TABLE_VERSION_OPTION_KEY, 0);
        return $version >= static::LATEST_TABLE_VERSION;
    }

    public static function install(): void
    {
        static::deleteUnusedTable();
        static::createTables();
    }

    public static function createTableTransaction(): void
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        $tableName = self::getBaseTableName(TransactionRepository::TABLE_NAME);
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

        DatabaseTableInstaller::createTable($sql, $tableName);
    }

    public static function createTableInscription(): void
    {
        global $wpdb;
        $charsetCollate = $wpdb->get_charset_collate();
        $tableName = self::getBaseTableName(InscriptionRepository::TABLE_NAME);
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

        DatabaseTableInstaller::createTable($sql, $tableName);
    }

    /**
     * Creates a database table.
     *
     * @param string $query     SQL query to create table.
     * @param string $tableName Logical name or identifier of the table.
     *
     * @throws CreateTableException
     * @throws \InvalidArgumentException
     */
    private static function createTable($query, $tableName): void
    {
        if (empty($query)) {
            throw new \InvalidArgumentException('La query para crear la tabla ' . $tableName . ' es obligatoria.');
        }

        global $wpdb;
        dbDelta($query);
        $lastError = $wpdb->last_error;
        if (!empty($lastError)) {
            throw new CreateTableException('Error al crear la tabla ' . $tableName .  ' en la base de datos: ' . $lastError);
        }
    }

    /**
     * Check if a needed tables exists.
     *
     * @throws CreateTableException
     */
    public static function checkTables(): void
    {
        $tables = [
            TransactionRepository::TABLE_NAME,
            InscriptionRepository::TABLE_NAME
        ];

        foreach ($tables as $tableName) {
            if (!static::tableExists($tableName)) {
                throw new CreateTableException(
                    "La tabla " . self::getBaseTableName($tableName) . " NO ha sido creada correctamente."
                );
            }
        }
    }

    private static function tableExists($tableName): bool
    {
        global $wpdb;
        return (new WpdbTableGateway($wpdb, $tableName))->tableExists();
    }

    public static function createTables()
    {
        static::createTableTransaction();
        static::createTableInscription();
        update_site_option(static::TABLE_VERSION_OPTION_KEY, static::LATEST_TABLE_VERSION);
    }

    public static function deleteTableByName($tableName)
    {
        global $wpdb;
        $sql = "DROP TABLE IF EXISTS `$tableName`";
        $wpdb->query($sql);
        delete_option(static::TABLE_VERSION_OPTION_KEY);
    }

    /**
     * Create tables if is necessary.
     *
     * @throws CreateTableException
     * @throws \InvalidArgumentException
     */
    public static function createTableIfNeeded()
    {
        if (!static::isUpgraded()) {
            static::install();
        }
    }

    public static function deleteTable()
    {
        static::deleteTableByName(self::getBaseTableName(TransactionRepository::TABLE_NAME));
        static::deleteTableByName(self::getBaseTableName(InscriptionRepository::TABLE_NAME));
    }

    public static function deleteUnusedTable()
    {
        static::deleteTableByName(self::getBaseTableName('transbank_api_service_log'));
        static::deleteTableByName(self::getBaseTableName('transbank_execution_error_log'));
    }

    public static function getBaseTableName($baseName): string
    {
        global $wpdb;
        if (is_multisite()) {
            return $wpdb->base_prefix . $baseName;
        } else {
            return $wpdb->prefix . $baseName;
        }
    }
}
