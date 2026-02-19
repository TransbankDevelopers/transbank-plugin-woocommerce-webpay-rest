<?php

namespace Transbank\WooCommerce\WebpayRest\Services;

use Transbank\WooCommerce\WebpayRest\Repositories\InscriptionRepository;
use Transbank\Webpay\Oneclick\Responses\InscriptionFinishResponse;
use Transbank\Plugin\Helpers\TbkConstants;
use Transbank\Plugin\Model\TbkInscription;
use Transbank\Plugin\Exceptions\DatabaseRecordCreationException;
use Transbank\Plugin\Exceptions\RecordNotFoundOnDatabaseException;

class InscriptionService
{
    private InscriptionRepository $repository;


    public function __construct(
        InscriptionRepository $repository
    ) {
        $this->repository = $repository;
    }

    /**
     * Create a inscription record.
     *
     * @param TbkInscription $data Inscription data to be stored.
     * @throws RecordNotFoundOnDatabaseException
     * @throws DatabaseRecordCreationException
     * @return mixed
     */
    public function createAndGet(TbkInscription $data): TbkInscription
    {
        try {
            $id = $this->repository->create([
                'token' => $data->token,
                'username' => $data->username,
                'order_id' => $data->orderId,
                'user_id' => $data->userId,
                'pay_after_inscription' => false,
                'email' => $data->email,
                'from' => $data->from,
                'status' => $data->status,
                'environment' => $data->environment,
                'commerce_code' => $data->commerceCode
            ]);

            $record = $this->repository->findById($id);
            if (!$record) {
                throw new RecordNotFoundOnDatabaseException('inscripción recien creada no fue encontrada');
            }

            return new TbkInscription($record);
        } catch (\Exception) {
            throw new DatabaseRecordCreationException("Error al crear el registro de Inscripción");
        }
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
        $this->repository->update($inscriptionId, $data);
    }

    /**
     * Retrieve a inscription by token. Throws an exception if not found.
     *
     * @param string $token The inscription token.
     * @return object
     * @throws \Transbank\Plugin\Exceptions\RecordNotFoundOnDatabaseException
     */
    public function getByToken(string $token): object
    {
        return $this->repository->getByToken($token);
    }


    /**
     * Retrieve a inscription by token. return null if not found.
     *
     * @param string $token The inscription token.
     * @return object|null
     */
    public function findByToken(string $token): ?object
    {
        return $this->repository->findByToken($token);
    }

    public function updateWithFinishResponse(string $inscriptionId, InscriptionFinishResponse $resp): void
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

    public function updateWithFinishResponseError(string $inscriptionId, string $error, string $detailError): void
    {
        $this->update($inscriptionId, [
            'status' => TbkConstants::INSCRIPTIONS_STATUS_FAILED,
            'error' => $error,
            'detail_error' => $detailError
        ]);
    }
}
