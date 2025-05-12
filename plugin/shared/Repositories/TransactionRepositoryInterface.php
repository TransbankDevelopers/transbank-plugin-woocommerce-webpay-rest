<?php

namespace Transbank\Plugin\Repositories;

interface TransactionRepositoryInterface
{
    public function create(array $data);
    public function update(string $transactionId, array $data);
    public function getByToken(string $token);
    public function getByBuyOrder(string $buyOrder);
    public function findFirstApprovedByOrderId(string $orderId);
    public function getByBuyOrderAndSessionId(string $buyOrder, string $sessionId);
    public function checkExistTable(): array;
}
