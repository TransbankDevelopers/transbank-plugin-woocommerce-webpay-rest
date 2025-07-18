<?php

namespace Transbank\Plugin\Services;

use Transbank\Plugin\Repositories\TransactionRepositoryInterface;
use Transbank\Plugin\Helpers\TbkConstants;
use Transbank\Plugin\Model\TbkTransaction;
use Transbank\Webpay\Oneclick\Responses\MallTransactionAuthorizeResponse;
use Transbank\Webpay\WebpayPlus\Responses\TransactionRefundResponse;
use Transbank\Webpay\Oneclick\Responses\MallTransactionRefundResponse;

class TransactionService
{
    private TransactionRepositoryInterface $repository;

    
    public function __construct(
        TransactionRepositoryInterface $repository
    ) {
        $this->repository = $repository;
    }

    /**
     * Create a transaction record.
     *
     * @param TbkTransaction $data Transaction data to be stored.
     * @return mixed
     */
    public function create(TbkTransaction $data): TbkTransaction
    {
        if ($data->getProduct() === TbkConstants::TRANSACTION_WEBPAY_PLUS) {
            $data->setChildBuyOrder('');
            $data->setChildCommerceCode('');
        }
        else {
            $data->setToken('');
            $data->setSessionId('');
        }
        $record = $this->repository->create($data);
        if ($record === null) {
            throw new \Exception("Problemas al crear el registro de Transacción");
        }
        return new TbkTransaction($record);
    }

    /**
     * Update an existing transaction record by id.
     *
     * @param string $transactionId Token identifying the transaction.
     * @param array $data New data to update the transaction with.
     * @return mixed
     */
    public function update(string $transactionId, array $data): mixed
    {
        return $this->repository->update($transactionId, $data);
    }

    /**
     * Retrieve a transaction by token. Throws an exception if not found.
     *
     * @param string $token The transaction token.
     * @return mixed
     * @throws \Transbank\Plugin\Exceptions\RecordNotFoundOnDatabaseException
     */
    public function getByToken(string $token): object
    {
        return $this->repository->getByToken($token);
    }

    /**
     * Retrieve the first transaction by token.
     *
     * @param mixed $token
     * @return object|null
     */
    public function findFirstByToken(string $token): object
    {
        return $this->repository->findFirstByToken($token);
    }

    /**
     * Retrieve a transaction by buyOrder. Throws an exception if not found.
     *
     * @param string $buyOrder The buy order associated with the transaction.
     * @return mixed
     * @throws \Transbank\Plugin\Exceptions\RecordNotFoundOnDatabaseException
     */
    public function getByBuyOrder(string $buyOrder): object
    {
        return $this->repository->getByBuyOrder($buyOrder);
    }

    /**
     * Retrieve a transaction by buyOrder. Throws an exception if not found.
     *
     * @param string $buyOrder The buy order associated with the transaction.
     * @return mixed
     * @throws \Transbank\Plugin\Exceptions\RecordNotFoundOnDatabaseException
     */
    public function findFirstApprovedByOrderId(string $orderId): ?object
    {
        return $this->repository->findFirstApprovedByOrderId($orderId);
    }

    /**
     * Retrieve a transaction by buyOrder and sessionId. Throws if not found.
     *
     * @param string $buyOrder
     * @param string $sessionId
     * @return mixed
     * @throws \Transbank\Plugin\Exceptions\RecordNotFoundOnDatabaseException
     */
    public function getByBuyOrderAndSessionId(string $buyOrder, string $sessionId): object
    {
        return $this->repository->getByBuyOrderAndSessionId($buyOrder, $sessionId);
    }

    /**
     * Retrieve the first transaction by orderId.
     *
     * @param mixed $orderId
     * @return object|null
     */
    public function findFirstByOrderId($orderId): ?object
    {
        return $this->repository->findFirstByOrderId($orderId);
    }

    /**
     * Checks if the transaction is already processed by the token.
     *
     * @param string $token The transaction token.
     *
     * @return bool
     */
    public function checkIsAlreadyProcessed(string $token): bool
    {
        $result = $this->repository->findFirstByToken($token);
        if (is_null($result)) {
            return false;
        }
        return $result->status != TbkConstants::TRANSACTION_STATUS_INITIALIZED;
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

    public function updateWithRefundResponse(string $transactionId, TransactionRefundResponse|MallTransactionRefundResponse $resp)
    {
        $this->update($transactionId, [
            'last_refund_type' => $resp->getType(),
            'last_refund_response' => json_encode($resp)
        ]);
    }

    public function updateWithAuthorizeResponse(string $transactionId, MallTransactionAuthorizeResponse $resp)
    {
        $this->update($transactionId, [
            'status' => TbkConstants::TRANSACTION_STATUS_APPROVED,
            'transbank_status' => $resp->getDetails()[0]->getStatus() ?? null,
            'transbank_response' => json_encode($resp),
        ]);
    }

    public function updateWithAuthorizeResponseError(string $transactionId, $error, $detailError)
    {
        $this->update($transactionId, [
            'status' => TbkConstants::TRANSACTION_STATUS_FAILED,
            'error' => $error,
            'detail_error' => $detailError
        ]);
    }

}
