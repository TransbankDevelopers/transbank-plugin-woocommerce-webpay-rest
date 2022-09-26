<?php

namespace Transbank\WooCommerce\WebpayRest\Models;

use function is_multisite;
use Transbank\WooCommerce\WebpayRest\Exceptions\TokenNotFoundOnDatabaseException;

class Inscription
{
    const INSCRIPTIONS_TABLE_NAME = 'transbank_inscriptions';
    const STATUS_INITIALIZED = 'initialized';
    const STATUS_FAILED = 'failed';
    const STATUS_COMPLETED = 'completed';

    public static function getTableName(): string
    {
        global $wpdb;
        if (is_multisite()) {
            return $wpdb->base_prefix.static::INSCRIPTIONS_TABLE_NAME;
        } else {
            return $wpdb->prefix.static::INSCRIPTIONS_TABLE_NAME;
        }
    }

    public static function create(array $data)
    {
        global $wpdb;

        return $wpdb->insert(static::getTableName(), $data);
    }

    public static function update($inscriptionId, array $data)
    {
        global $wpdb;

        return $wpdb->update(static::getTableName(), $data, ['id' => $inscriptionId]);
    }

    /**
     * @throws TokenNotFoundOnDatabaseException
     */
    public static function getByToken($token)
    {
        global $wpdb;
        $inscriptionTableName = static::getTableName();
        $sql = $wpdb->prepare("SELECT * FROM $inscriptionTableName WHERE `token` = '%s'", $token);
        $sqlResult = $wpdb->get_results($sql);
        if (!is_array($sqlResult) || count($sqlResult) <= 0) {
            throw new TokenNotFoundOnDatabaseException("Token '{$token}' no se encontró en la base de datos de transacciones, por lo que no se puede completar el proceso");
        }

        return $sqlResult[0] ?? null;
    }

    public static function checkExistTable()
    {
        global $wpdb;
        $transactionTable = static::getTableName();
        $sql = "SELECT COUNT(1) FROM ".$transactionTable;
        try {
            $sqlResult = $wpdb->get_results($sql);
            $success = empty($wpdb->last_error);
            if (!$success) {
                return array('ok' => false, 'error' => "La tabla '{$transactionTable}' no se encontró en la base de datos.", 'exception' => "{$wpdb->last_error}");
            }
        }
        catch(Exception $e) {
            return array('ok' => false, 'error' => "La tabla '{$transactionTable}' no se encontró en la base de datos.", 'exception' => "{$e->getMessage()}");
        }
        return array('ok' => true, 'msg' => "La tabla '{$transactionTable}' existe.");
    }
}
