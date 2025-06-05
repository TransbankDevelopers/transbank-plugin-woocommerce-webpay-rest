<?php

namespace Transbank\Plugin\Services;

use Transbank\Plugin\Repositories\TransactionRepositoryInterface;
use Transbank\Plugin\Helpers\TbkConstants;
use Transbank\Plugin\Model\TbkTransaction;

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
    public function create(TbkTransaction $data): mixed
    {
        if ($data->getProduct() === TbkConstants::TRANSACTION_WEBPAY_PLUS) {
            $data->setChildBuyOrder('');
            $data->setChildCommerceCode('');
        }
        else {
            $data->setToken('');
            $data->setSessionId('');
        }
        return $this->repository->create($data);
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

}
