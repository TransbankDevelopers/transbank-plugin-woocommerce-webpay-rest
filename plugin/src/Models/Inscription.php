<?php

namespace Transbank\WooCommerce\WebpayRest\Models;

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
    public static function findByToken($token)
    {
        global $wpdb;
        $inscriptionTableName = static::getTableName();
        $sql = $wpdb->prepare("SELECT * FROM $inscriptionTableName WHERE `token` = '%s'", $token);
        return $wpdb->get_results($sql);
    }

    public static function checkExistTable()
    {
        return static::checkExistTableBase(static::getTableName());
    }
}
