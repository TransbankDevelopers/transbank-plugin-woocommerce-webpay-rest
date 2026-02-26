<?php

namespace Transbank\WooCommerce\WebpayRest\Repositories;

use Transbank\WooCommerce\WebpayRest\Exceptions\DatabaseInsertException;
use Transbank\Plugin\Exceptions\RecordNotFoundOnDatabaseException;
use Transbank\Plugin\Model\TbkInscription;
use Transbank\WooCommerce\WebpayRest\Infrastructure\Database\WpdbTableGateway;
use Transbank\WooCommerce\WebpayRest\Infrastructure\Database\WpdbTableNames;

class InscriptionRepository
{
    const TABLE_NAME = 'transbank_inscriptions';

    private WpdbTableGateway $db;
    private WpdbTableNames $tableNames;

    public function __construct(WpdbTableGateway $wpdb, WpdbTableNames $tableNames)
    {
        $this->db = $wpdb;
        $this->tableNames = $tableNames;
    }

    /**
     * Get the name of the inscription database table without prefix.
     *
     * @return string The name of the table used to store inscriptions.
     */

    public function getRawTableName(): string
    {
        return self::TABLE_NAME;
    }
    /**
     * Create a inscription record and return id.
     *
     * @param array $data Inscription data to be stored.
     * @return int
     * @throws RecordNotFoundOnDatabaseException
     * @throws \InvalidArgumentException
     * @throws DatabaseInsertException
     */
    public function create(array $data): int
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('No se proporcionaron datos para insertar.');
        }

        return $this->db->insert($data, 'Error al insertar en la tabla ');
    }

    /**
     * Update an existing inscription record by id.
     *
     * @param string $inscriptionId Token identifying the inscription.
     * @param array $data New data to update the transaction with.
     *
     * @throws \Transbank\WooCommerce\WebpayRest\Exceptions\DatabaseUpdateException if update fails.
     */
    public function update(string $inscriptionId, array $data): void
    {
        $this->db->update(['id' => $inscriptionId], $data);
    }

    /**
     * Delete a inscription record by id.
     *
     * @param int $id Inscription ID.
     * @return int|bool Number of rows deleted or false on failure.
     */
    public function deleteById(int $id): int|bool
    {
        return $this->db->deleteById($id);
    }

    /**
     * Retrieve a inscription by token. Throws an exception if not found.
     *
     * @param string $token The inscription token.
     * @return object Inscription object
     * @throws RecordNotFoundOnDatabaseException
     * @throws \InvalidArgumentException
     */
    public function getByToken(string $token): object
    {
        return $this->db->getOne(
            "SELECT * FROM `{$this->db->getTableName()}` WHERE `token` = %s",
            [$token],
            'Registro no encontrado'
        );
    }

    /**
     * Retrieve a inscription by token. return null if not found.
     *
     * @param string $token The inscription token.
     * @return object|null Inscription object
     */
    public function findByToken(string $token): ?object
    {
        try {
            return $this->getByToken($token);
        } catch (RecordNotFoundOnDatabaseException | \InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Retrieve a inscription by ID. return null if not found.
     *
     * @param int $id The inscription ID.
     * @return object|null Inscription object
     */
    public function findById(int $id): ?object
    {
        return $this->db->findOne(
            "SELECT * FROM `{$this->db->getTableName()}` WHERE `id` = %d",
            [$id],
        );
    }

    /**
     * Retrieve a inscription by payment token ID. return null if not found.
     *
     * @param int $paymentTokenId The payment token ID.
     * @return TbkInscription|null Inscription model
     */
    public function findByPaymentTokenId(int $paymentTokenId): ?TbkInscription
    {
        $record = $this->db->findOne(
            "SELECT * FROM `{$this->db->getTableName()}` WHERE `token_id` = %d",
            [$paymentTokenId],
        );

        if (!$record) {
            return null;
        }

        return new TbkInscription($record);
    }

    /**
     * List finished inscriptions by environment with pagination.
     *
     * @param string $environment
     * @param int $offset
     * @param int $limit
     * @return array<int,object>
     */
    public function listFinishedByEnvironment(string $environment, int $offset, int $limit): array
    {
        return $this->db->find(
            "SELECT i.*, u.user_login AS user
             FROM `{$this->db->getTableName()}` i
             LEFT JOIN `{$this->tableNames->getUsersTableName()}` u ON u.ID = i.user_id
             WHERE i.finished = 1 AND i.response_code = 0 AND i.environment = %s
             LIMIT %d, %d",
            [$environment, $offset, $limit]
        );
    }

    /**
     * Count finished inscriptions by environment.
     *
     * @param string $environment
     * @return int
     */
    public function countFinishedByEnvironment(string $environment): int
    {
        $row = $this->db->findOne(
            "SELECT COUNT(*) AS total
             FROM `{$this->db->getTableName()}`
             WHERE finished = 1 AND response_code = 0 AND environment = %s",
            [$environment]
        );

        return (int) ($row->total ?? 0);
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
        $this->db->runInTransaction($callback);
    }
}
