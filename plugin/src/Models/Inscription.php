<?php

namespace Transbank\WooCommerce\WebpayRest\Models;

use Transbank\Plugin\Exceptions\TokenNotFoundOnDatabaseException;

class Inscription extends BaseModel
{
    const INSCRIPTIONS_TABLE_NAME = 'transbank_inscriptions';
    const STATUS_INITIALIZED = 'initialized';
    const STATUS_FAILED = 'failed';
    const STATUS_COMPLETED = 'completed';

    /**
     * @return string
     */
    public static function getTableName(): string
    {
        return static::getBaseTableName(static::INSCRIPTIONS_TABLE_NAME);
    }

    public static function create(array $data)
    {
        return static::insertBase(static::getTableName(), $data);
    }

    public static function update($inscriptionId, array $data)
    {
        return static::updateBase(static::getTableName(), $inscriptionId, $data);
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
        return static::checkExistTableBase(static::getTableName());
    }
}
