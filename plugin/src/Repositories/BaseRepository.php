<?php

namespace Transbank\WooCommerce\WebpayRest\Repositories;

abstract class BaseRepository
{
    abstract protected function getTableName(): string;
    protected function getBaseTableName($baseName): string
    {
        global $wpdb;
        if (is_multisite()) {
            return $wpdb->base_prefix.$baseName;
        } else {
            return $wpdb->prefix.$baseName;
        }
    }

    protected function insertBase(array $data)
    {
        global $wpdb;
        return $wpdb->insert($this->getTableName(), $data);
    }

    protected function updateBase($id, array $data)
    {
        global $wpdb;

        return $wpdb->update($this->getTableName(),  $data, ['id' => $id]);
    }

    /**
     * Check if the transaction table exists in the database.
     *
     * @return array
     */
    public function checkExistTable(): array
    {
        $tableName = $this->getTableName();
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

    /**
     * Get transactions by custom conditions.
     *
     * @param array $conditions         Key-value pairs of column names and values.
     * @param string|string[] $orderBy  Column name or an array of column names to order by.
     * @param string $orderDirection    Order direction ('ASC' or 'DESC').
     *
     * @return array|null Transaction objects array.
     */
    protected function getTransactionsByConditions(array $conditions, string $orderBy = '', string $orderDirection = 'DESC'): ?array
    {
        global $wpdb;
        $tableName = static::getTableName();

        $whereClauses = [];
        foreach ($conditions as $column => $value) {
            $whereClauses[] = "`$column` = %s";
            $values[] = $value;
        }

        $whereSql = implode(' AND ', $whereClauses);
        $sql = "SELECT * FROM {$tableName} WHERE {$whereSql}";
        $safeSql = $wpdb->prepare($sql, $values);

        if (!empty($orderBy)) {
            if (is_array($orderBy)) {
                $orderBy = implode(", ", array_map('esc_sql', $orderBy));
            } else {
                $orderBy = esc_sql($orderBy);
            }
            $orderDirection = strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC';
            $safeSql .= " ORDER BY {$orderBy} {$orderDirection}";
        }

        return $wpdb->get_results($safeSql);
    }

    protected function executeQuery($query, ...$args)
    {
        global $wpdb;
        $sql = $wpdb->prepare($query, $args);
        return $wpdb->get_results($sql);
    }

    protected function findFirst($query, ...$args): ?object
    {
        $result = $this->executeQuery($query, ...$args);
        if (!is_array($result) || empty($result)) {
            return null;
        }
        return $result[0];
    }
}
