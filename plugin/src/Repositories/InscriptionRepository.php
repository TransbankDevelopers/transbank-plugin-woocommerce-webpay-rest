<?php

namespace Transbank\WooCommerce\WebpayRest\Repositories;

use Transbank\Plugin\Repositories\InscriptionRepositoryInterface;
use Transbank\Plugin\Exceptions\RecordNotFoundOnDatabaseException;

class InscriptionRepository extends BaseRepository implements InscriptionRepositoryInterface
{
    const TABLE_NAME = 'transbank_inscriptions';

    /**
     * Get the name of the inscription database table.
     *
     * @return string The name of the table used to store inscriptions.
     */
    public function getTableName(): string
    {
        return $this->getBaseTableName(static::TABLE_NAME);
    }

    /**
     * Create a inscription record.
     *
     * @param array $data Inscription data to be stored.
     * @return mixed
     */
    public function create(array $data)
    {
        return $this->insertBase($data);
    }

    /**
     * Update an existing inscription record by id.
     *
     * @param string $inscriptionId Token identifying the inscription.
     * @param array $data New data to update the transaction with.
     * @return mixed
     */
    public function update(string $inscriptionId, array $data)
    {
        return $this->updateBase($inscriptionId, $data);
    }

    /**
     * Retrieve a inscription by token. Throws an exception if not found.
     *
     * @param string $token The inscription token.
     * @return mixed
     * @throws RecordNotFoundOnDatabaseException
     */
    public function getByToken(string $token)
    {
        $inscriptionTableName = $this->getTableName();
        return $this->getFirst(
            "SELECT * FROM $inscriptionTableName WHERE `token` = '%s'",
            "Token no se encontró en la base de datos de inscripciones",
            $token
        );
    }

    /**
     * Retrieve a inscription by ID. Throws an exception if not found.
     *
     * @param string $id The inscription ID.
     * @return object Inscription object
     *
     * @throws RecordNotFoundOnDatabaseException
     */
    public function getById(string $id): object
    {
        $inscriptionTableName = $this->getTableName();
        return $this->getFirst(
            "SELECT * FROM $inscriptionTableName WHERE `id` = '%s'",
            "No se encontró inscripción con el ID proporcionado",
            $id
        );
    }
}
