<?php

namespace Transbank\Plugin\Repositories;

use Transbank\Plugin\Model\TbkTransaction;

interface TransactionRepositoryInterface
{
    public function getTableName(): string;
    public function createWebpay(TbkTransaction $data);
    public function createOneclick(array $data);
    public function update(string $transactionId, array $data);
    public function getByToken(string $token);
    public function getByBuyOrder(string $buyOrder);
    public function findFirstApprovedByOrderId(string $orderId);
    public function getByBuyOrderAndSessionId(string $buyOrder, string $sessionId);
    public function checkExistTable(): array;
    public function findFirstByOrderId($orderId): ?object;
    public function findFirstByToken($token): ?object;
}
