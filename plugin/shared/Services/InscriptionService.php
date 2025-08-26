<?php

namespace Transbank\Plugin\Services;

use Transbank\Plugin\Repositories\InscriptionRepositoryInterface;
use Transbank\Webpay\Oneclick\Responses\InscriptionFinishResponse;
use Transbank\Plugin\Helpers\TbkConstants;
use Transbank\Plugin\Model\TbkInscription;
use Transbank\Plugin\Exceptions\DatabaseRecordCreationException;

class InscriptionService
{
    private InscriptionRepositoryInterface $repository;


    public function __construct(
        InscriptionRepositoryInterface $repository
    ) {
        $this->repository = $repository;
    }

    /**
     * Create a inscription record.
     *
     * @param TbkInscription $data Inscription data to be stored.
     * @return mixed
     */
    public function create(TbkInscription $data): TbkInscription
    {
        try {
            $record = $this->repository->create([
                'token' => $data->getToken(),
                'username' => $data->getUsername(),
                'order_id' => $data->getOrderId(),
                'user_id' => $data->getUserId(),
                'pay_after_inscription' => false,
                'email' => $data->getEmail(),
                'from' => $data->getFrom(),
                'status' => $data->getStatus(),
                'environment' => $data->getEnvironment(),
                'commerce_code' => $data->getCommerceCode()
            ]);

            return new TbkInscription($record);
        } catch (\Exception $e) {
            throw new DatabaseRecordCreationException("Error al crear el registro de Inscripción");
        }
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
        return $this->repository->update($inscriptionId, $data);
    }

    /**
     * Retrieve a inscription by token. Throws an exception if not found.
     *
     * @param string $token The inscription token.
     * @return mixed
     * @throws \Transbank\Plugin\Exceptions\RecordNotFoundOnDatabaseException
     */
    public function getByToken(string $token)
    {
        return $this->repository->getByToken($token);
    }

    /**
     * Check if the transaction table exists in the database.
     *
     * @return array
     */
    public function existsTransactionTable(): array
    {
        return $this->repository->checkExistTable();
    }

    /**
     * Returns the name of the table associated with the repository.
     *
     * @return string Name of the database table.
     */
    public function getTableName(): string
    {
        return $this->repository->getTableName();
    }

    public function updateWithFinishResponse(string $inscriptionId, InscriptionFinishResponse $resp)
    {
        $this->update($inscriptionId, [
            'finished' => true,
            'authorization_code' => $resp->getAuthorizationCode(),
            'card_type' => $resp->getCardType(),
            'card_number' => $resp->getCardNumber(),
            'transbank_response' => json_encode($resp),
            'response_code' => $resp->getResponseCode(),
            'status' => $resp->isApproved() ? TbkConstants::INSCRIPTIONS_STATUS_COMPLETED : TbkConstants::INSCRIPTIONS_STATUS_FAILED
        ]);
    }

    public function updateWithFinishResponseError(string $inscriptionId, $error, $detailError)
    {
        $this->update($inscriptionId, [
            'status' => TbkConstants::INSCRIPTIONS_STATUS_FAILED,
            'error' => $error,
            'detail_error' => $detailError
        ]);
    }

}

