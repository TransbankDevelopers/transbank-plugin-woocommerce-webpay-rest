<?php

namespace Transbank\WooCommerce\WebpayRest\Repositories;

use Transbank\WooCommerce\WebpayRest\Exceptions\DatabaseInsertException;
use Transbank\Plugin\Exceptions\RecordNotFoundOnDatabaseException;
use Transbank\Plugin\Model\TbkInscription;
use Transbank\WooCommerce\WebpayRest\Infrastructure\Database\WpdbTableGateway;

class InscriptionRepository
{
    const TABLE_NAME = 'transbank_inscriptions';

    private WpdbTableGateway $db;

    public function __construct(WpdbTableGateway $wpdb)
    {
        $this->db = $wpdb;
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
    public function insert(array $data): int
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
     * @return int|bool Number of rows updated or false on failure.
     */
    public function update(string $inscriptionId, array $data): int|bool
    {
        return $this->db->update(['id' => $inscriptionId], $data);
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
}
