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

    public function create(TbkTransaction $data): mixed
    {
        return $this->repository->create($data);
    }

    public function update(string $transactionId, array $data): mixed
    {
        return $this->repository->update($transactionId, $data);
    }

    public function getByToken(string $token): object
    {
        return $this->repository->getByToken($token);
    }

    public function findFirstByToken(string $token): object
    {
        return $this->repository->findFirstByToken($token);
    }

    public function getByBuyOrder(string $buyOrder): object
    {
        return $this->repository->getByBuyOrder($buyOrder);
    }

    public function findFirstApprovedByOrderId(string $orderId): ?object
    {
        return $this->repository->findFirstApprovedByOrderId($orderId);
    }

    public function getByBuyOrderAndSessionId(string $buyOrder, string $sessionId): object
    {
        return $this->repository->getByBuyOrderAndSessionId($buyOrder, $sessionId);
    }

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

    public function existsTransactionTable(): array
    {
        return $this->repository->checkExistTable();
    }

    public function getTableName(): string
    {
        return $this->repository->getTableName();
    }

}
