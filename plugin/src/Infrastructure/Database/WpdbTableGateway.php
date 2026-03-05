<?php

namespace Transbank\WooCommerce\WebpayRest\Infrastructure\Database;

use InvalidArgumentException;
use Transbank\Plugin\Exceptions\RecordNotFoundOnDatabaseException;
use Transbank\WooCommerce\WebpayRest\Exceptions\DatabaseInsertException;
use Transbank\WooCommerce\WebpayRest\Exceptions\DatabaseUpdateException;
use wpdb;

/**
 *
 * - Builds the full table name using WP prefix/base_prefix (multisite aware).
 * - Provides strict prepare() usage when args are provided.
 * - Sanitizes values before insert/update.
 */
final class WpdbTableGateway
{
    /** Full table name including WP prefix. */
    private string $table;

    private wpdb $db;

    /**
     * Whitelist of column names whose values must be treated as JSON.
     *
     * @var array<string,true>
     */
    private array $jsonFields;

    /**
     * @param wpdb  $db WordPress database instance.
     * @param string $baseTableName Base table name without prefix.
     * @param array<int,string> $jsonFields Column names treated as JSON.
     *
     * @throws InvalidArgumentException When $baseTableName is not a valid identifier.
     */
    public function __construct(wpdb $db, string $baseTableName, array $jsonFields = [])
    {
        $this->db = $db;
        $this->assertIdentifier($baseTableName);

        $prefix = is_multisite() ? $db->base_prefix : $db->prefix;
        $prefix = preg_replace('/\W/', '', (string) $prefix);

        $this->table = $prefix . $baseTableName;

        $this->jsonFields = [];
        foreach ($jsonFields as $field) {
            $field = (string) $field;
            if ($field !== '') {
                $this->jsonFields[$field] = true;
            }
        }
    }

    /**
     * Runs a SELECT query and returns results as an array of objects.
     *
     * @param string $sql  SQL with optional placeholders (%s/%d/%f).
     * @param array<int,mixed> $args Values for placeholders.
     * @return array<int,object>
     */
    public function find(string $sql, array $args = []): array
    {
        try {
            $query = $this->prepareStrict($sql, $args);
            return $this->db->get_results($query) ?: [];
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Runs a SELECT query and returns a single row as an object (or null).
     *
     * @param string $sql  SQL with optional placeholders (%s/%d/%f).
     * @param array $args Values for placeholders.
     * @return object|null
     */
    public function findOne(string $sql, array $args = []): ?object
    {
        try {
            $query = $this->prepareStrict($sql, $args);
            return $this->db->get_row($query) ?: null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Same as findOne(), but throws if the row is not found.
     *
     * @param string $sql
     * @param array $args
     * @param string|null $error Custom error message (optional).
     * @param bool $addSqlError Append $wpdb->last_error (optional).
     * @return object
     *
     * @throws InvalidArgumentException If args are provided but SQL has no placeholders.
     * @throws RecordNotFoundOnDatabaseException When no row is found.
     */
    public function getOne(
        string $sql,
        array $args = [],
        ?string $error = null,
        bool $addSqlError = false
    ): object {
        $row = $this->findOne($sql, $args);

        if (!$row) {
            $errorMessage = $error ?? '';

            if ($addSqlError) {
                $errorMessage .= $this->db->last_error;
            }

            throw new RecordNotFoundOnDatabaseException($errorMessage);
        }

        return $row;
    }

    /**
     * Inserts a row into the table.
     *
     * @param array<string,mixed> $data Column => value.
     * @param string|null $error Custom error prefix.
     * @return int Inserted ID.
     *
     * @throws DatabaseInsertException If insert fails.
     */
    public function insert(array $data, ?string $error = null): int
    {
        $safeData = $this->sanitizeData($data);

        if ($this->db->insert($this->table, $safeData) === false) {
            $errorMessage = $error ?? '';
            $errorMessage .= $this->db->last_error;

            throw new DatabaseInsertException($errorMessage);
        }

        return (int) $this->db->insert_id;
    }

    /**
     * Updates rows in the table.
     *
     * @param array<string,mixed> $where Where clause (Column => value).
     * @param array<string,mixed> $data  Column => value.
     * @param string|null $error Custom error prefix.
     *
     * @throws DatabaseUpdateException If update fails.
     */
    public function update(array $where, array $data, ?string $error = null): void
    {
        $result = $this->db->update(
            $this->table,
            $this->sanitizeData($data),
            $this->sanitizeData($where)
        );

        if ($result === false) {
            $errorMessage = $error ?? '';
            $errorMessage .= $this->db->last_error;

            throw new DatabaseUpdateException($errorMessage);
        }
    }

    /**
     * Deletes a row by ID.
     *
     * @param int $id
     * @return int|false Number of deleted rows, or false on error.
     */
    public function deleteById(int $id): int|false
    {
        return $this->db->delete($this->table, ['id' => $id], ['%d']);
    }

    /**
     * Checks whether the table exists and is queryable.
     *
     * @return bool
     */
    public function tableExists(): bool
    {
        $tableSql = $this->escapeIdentifier($this->table);
        $this->db->query("SELECT 1 FROM {$tableSql} LIMIT 1");

        return $this->db->last_error === '';
    }

    /**
     * Returns the full table name (including prefix).
     */
    public function getTableName(): string
    {
        return $this->table;
    }

    /**
     * Run a callback within a database transaction.
     *
     * @param callable $callback
     * @return void
     * @throws \Throwable
     */
    public function runInTransaction(callable $callback): void
    {
        $this->db->query('START TRANSACTION');

        try {
            $callback();
            $this->db->query('COMMIT');
        } catch (\Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    private function castArgs(array $args): array
    {
        return array_map(function ($v) {
            if (is_array($v) || is_object($v)) {
                throw new InvalidArgumentException('Only scalar values or null are allowed.');
            }

            $out = $v;

            if (is_bool($v)) {
                $out = (int) $v;
            } elseif ($v !== null && !is_int($v) && !is_float($v)) {
                $out = (string) $v;
            }

            return $out;
        }, $args);
    }

    /**
     * Prepares SQL strictly: if args are provided, SQL must contain placeholders.
     *
     * @param string $sql
     * @param array $args
     * @return string
     *
     * @throws InvalidArgumentException
     */
    private function prepareStrict(string $query, array $args = []): string
    {
        $placeholders = [];

        preg_match_all('/(?<!%)%[sdf]/', $query, $placeholders);

        $placeholderCount = count($placeholders[0]);
        $argsCount = count($args);

        if ($placeholderCount > 0 && $argsCount === 0) {
            throw new InvalidArgumentException(
                'SQL query contains placeholders but no arguments were provided.'
            );
        }

        if ($placeholderCount !== $argsCount) {
            throw new InvalidArgumentException(
                sprintf(
                    'SQL placeholder count (%d) does not match arguments count (%d).',
                    $placeholderCount,
                    $argsCount
                )
            );
        }

        if ($placeholderCount === 0) {
            return $query;
        }

        return $this->db->prepare($query, ...$this->castArgs($args));
    }

    /**
     * Validates SQL identifiers (table/column-like names).
     *
     * @throws InvalidArgumentException
     */
    private function assertIdentifier(string $name): void
    {
        if ($name === '' || !preg_match('/^\w+$/', $name)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid identifier: %s',
                $name
            ));
        }
    }

    /**
     * Escapes an identifier for safe SQL usage.
     */
    private function escapeIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Sanitizes data values.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function sanitizeData(array $data): array
    {
        $out = [];

        foreach ($data as $k => $v) {
            $key = (string) $k;
            $this->assertIdentifier($key);
            $out[$key] = $this->sanitizeValueByKey($key, $v);
        }

        return $out;
    }

    /**
     * Sanitizes a value according to its column name.
     *
     * - For JSON fields: Skip sanitize step.
     * - For scalars: uses sanitize_text_field for strings.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    private function sanitizeValueByKey(string $key, mixed $value): mixed
    {
        $out = $value;

        if (isset($this->jsonFields[$key])) {
            return $out;
        } elseif (is_bool($value)) {
            $out = (int) $value;
        } elseif (is_string($value)) {
            $out = sanitize_text_field($value);
        }

        return $out;
    }
}
