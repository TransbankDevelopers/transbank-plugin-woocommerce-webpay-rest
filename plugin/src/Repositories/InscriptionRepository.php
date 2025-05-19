<?php

namespace Transbank\WooCommerce\WebpayRest\Repositories;

use Transbank\Plugin\Repositories\InscriptionRepositoryInterface;
use Transbank\WooCommerce\WebpayRest\Models\Inscription;
use Transbank\Plugin\Exceptions\RecordNotFoundOnDatabaseException;

class InscriptionRepository implements InscriptionRepositoryInterface
{

    /**
     * Get the name of the inscription database table.
     *
     * @return string The name of the table used to store inscriptions.
     */
    public function getTableName(): string
    {
        return Inscription::getTableName();
    }

    /**
     * Create a inscription record.
     *
     * @param array $data Inscription data to be stored.
     * @return mixed
     */
    public function create(array $data)
    {
        return Inscription::create($data);
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
        return Inscription::update($inscriptionId, $data);
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
        $result = Inscription::findByToken($token);
        if (!is_array($result) || empty($result)) {
            throw new RecordNotFoundOnDatabaseException(
                "Token no se encontró en la base de datos de inscripciones");
        }
        return $result[0];
    }

    /**
     * Check if the inscription table exists in the database.
     *
     * @return array
     */
    public function checkExistTable(): array
    {
        return Inscription::checkExistTable();
    }
}
