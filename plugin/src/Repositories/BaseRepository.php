<?php

namespace Transbank\WooCommerce\WebpayRest\Repositories;

use InvalidArgumentException;
use Throwable;
use Transbank\Plugin\Exceptions\RecordNotFoundOnDatabaseException;
use Transbank\WooCommerce\WebpayRest\Exceptions\DatabaseInsertException;

abstract class BaseRepository
{
    /**
     * Returns the table name for the repository.
     *
     * @return string
     */
    abstract protected function getTableName(): string;
    /**
     * Returns the base table name, considering multisite installations.
     *
     * @param string $baseName The base name of the table.
     *
     * @return string
     */
    protected function getBaseTableName(string $baseName): string
    {
        global $wpdb;
        if (is_multisite()) {
            return $wpdb->base_prefix . $baseName;
        } else {
            return $wpdb->prefix . $baseName;
        }
    }

    /**
     * Inserts a record into the base table and returns the inserted object.
     * Validates errors and applies security best practices.
     * Throws an exception if an error occurs.
     *
     * @param array $data Data to insert into the table.
     *
     * @return object The inserted row object.
     * @throws InvalidArgumentException If no data is provided.
     * @throws DatabaseInsertException If the insert fails or the row cannot be retrieved.
     */
    protected function insertBase(array $data): object
    {
        global $wpdb;
        $table = $this->getTableName();

        if (empty($data)) {
            throw new InvalidArgumentException('No se proporcionaron datos para insertar.');
        }

        $sanitizedData = array_map('sanitize_text_field', $data);
        $inserted = $wpdb->insert($table, $sanitizedData);
        if ($inserted === false) {
            throw new DatabaseInsertException(
                'Error al insertar en la tabla ' . $table . ': ' . $wpdb->last_error
            );
        }

        $id = $wpdb->insert_id;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            OBJECT
        );
        if (!$row) {
            throw new DatabaseInsertException(
                'No se pudo recuperar el registro insertado en la tabla ' . $table
            );
        }
        return $row;
    }

    /**
     * Updates a record in the base table by ID.
     *
     * @param int|string $id The record ID to update.
     * @param array $data Data to update in the record.
     *
     * @return int|false Number of rows updated or false on failure.
     */
    protected function updateBase(int|string $id, array $data): int|false
    {
        global $wpdb;
        return $wpdb->update($this->getTableName(), $data, ['id' => $id]);
    }

    /**
     * Checks if the transaction table exists in the database.
     *
     * @return array Status information about the table existence.
     */
    public function checkExistTable(): array
    {
        $tableName = $this->getTableName();
        global $wpdb;
        $sql = "SELECT COUNT(1) FROM " . $tableName;
        try {
            $wpdb->get_results($sql);
            $success = empty($wpdb->last_error);
            if (!$success) {
                return array(
                    'ok' => false,
                    'error' => "La tabla '{$tableName}' no se encontró en la base de datos.",
                    'exception' => "{$wpdb->last_error}"
                );
            }
        } catch (\Exception $e) {
            return array(
                'ok' => false,
                'error' => "La tabla '{$tableName}' no se encontró en la base de datos.",
                'exception' => "{$e->getMessage()}"
            );
        }
        return array('ok' => true, 'msg' => "La tabla '{$tableName}' existe.");
    }

    /**
     * Retrieves transactions by custom conditions.
     *
     * @param array $conditions Key-value pairs of column names and values.
     * @param string|string[] $orderBy Column name or an array of column names to order by.
     * @param string $orderDirection Order direction ('ASC' or 'DESC').
     * @return array|null Array of transaction objects or null.
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

    /**
     * Executes a prepared SQL query and returns the results.
     *
     * @param string $query The SQL query to execute.
     * @param mixed ...$args Arguments for the prepared statement.
     * @return array|null Array of result objects or null.
     */
    protected function executeQuery(string $query, mixed ...$args): array|null
    {
        global $wpdb;
        $sql = $wpdb->prepare($query, $args);
        return $wpdb->get_results($sql);
    }

    /**
     * Finds and returns the first result of a query.
     *
     * @param string $query The SQL query to execute.
     * @param mixed ...$args Arguments for the prepared statement.
     * @return object|null The first result object or null.
     */
    protected function findFirst(string $query, mixed ...$args): object|null
    {
        $result = $this->executeQuery($query, ...$args);
        if (!is_array($result) || empty($result)) {
            return null;
        }
        return $result[0];
    }

    /**
     * Retrieves the first record from a query. Throws if not found.
     *
     * @param string $query The SQL query to execute.
     * @param string $errorMessage Error message for the exception if not found.
     * @param mixed ...$args Arguments for the prepared statement.
     * @return object The first result object.
     * @throws RecordNotFoundOnDatabaseException If no record is found.
     */
    public function getFirst(string $query, string $errorMessage, mixed ...$args): object
    {
        $result = $this->findFirst($query, ...$args);
        if (is_null($result)) {
            throw new RecordNotFoundOnDatabaseException($errorMessage);
        }
        return $result;
    }
}
