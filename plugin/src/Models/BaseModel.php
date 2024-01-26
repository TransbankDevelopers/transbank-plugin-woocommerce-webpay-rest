<?php

namespace Transbank\WooCommerce\WebpayRest\Models;

use function is_multisite;

class BaseModel
{

    public static function getBaseTableName($baseName): string
    {
        global $wpdb;
        if (is_multisite()) {
            return $wpdb->base_prefix.$baseName;
        } else {
            return $wpdb->prefix.$baseName;
        }
    }

    public static function insertBase($tableName, array $data)
    {
        global $wpdb;
        return $wpdb->insert($tableName, $data);
    }

    public static function updateBase($tableName, $id, array $data)
    {
        global $wpdb;

        return $wpdb->update($tableName, $data, ['id' => $id]);
    }

    public static function checkExistTableBase($tableName)
    {
        global $wpdb;
        $sql = "SELECT COUNT(1) FROM ".$tableName;
        try {
            $wpdb->get_results($sql);
            $success = empty($wpdb->last_error);
            if (!$success) {
                return array('ok' => false,
                    'error' => "La tabla '{$tableName}' no se encontró en la base de datos.",
                    'exception' => "{$wpdb->last_error}");
            }
        } catch (\Exception $e) {
            return array('ok' => false,
                'error' => "La tabla '{$tableName}' no se encontró en la base de datos.",
                'exception' => "{$e->getMessage()}");
        }
        return array('ok' => true, 'msg' => "La tabla '{$tableName}' existe.");
    }

}
