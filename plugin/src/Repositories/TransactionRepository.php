<?php

namespace Transbank\WooCommerce\WebpayRest\Repositories;

use Transbank\Plugin\Repositories\TransactionRepositoryInterface;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\Plugin\Exceptions\TokenNotFoundOnDatabaseException;

class TransactionRepository implements TransactionRepositoryInterface
{

    /**
     * Update an existing transaction record by its ID.
     *
     * @param int $transactionId
     * @param array $data
     * @return mixed
     */
    public function create(array $data)
    {
        return Transaction::create($data);
    }

    /**
     * Update an existing transaction record by its ID.
     *
     * @param string $transactionId
     * @param array $data
     * @return mixed
     */
    public function update(string $transactionId, array $data)
    {
        return Transaction::update($transactionId, $data);
    }

     /**
     * Retrieve a transaction by buy order. Throws if not found.
     *
     * @param string $buyOrder
     * @return mixed
     * @throws TokenNotFoundOnDatabaseException
     */
    public function getByToken(string $token)
    {
        $result = Transaction::findByToken($token);
        if (!is_array($result) || count($result) <= 0) {
            throw new TokenNotFoundOnDatabaseException("Token '{$token}' no se encontró en la base de datos de transacciones, por lo que no se puede completar el proceso");
        }
        return $result[0];
    }

     /**
     * Retrieve a transaction by buy order. Throws if not found.
     *
     * @param string $buyOrder
     * @return mixed
     * @throws TokenNotFoundOnDatabaseException
     */
    public function getByBuyOrder(string $buyOrder)
    {
        $result = Transaction::findByBuyOrder($buyOrder);
        if (!is_array($result) || count($result) <= 0) {
            throw new TokenNotFoundOnDatabaseException("BuyOrder '{$buyOrder}' no se encontró en la base de datos de transacciones, por lo que no se puede completar el proceso");
        }
        return $result[0];
    }

    /**
     * Retrieve the first approved transaction by order ID.
     *
     * @param string $orderId
     * @return mixed|null
     */
    public function findFirstApprovedByOrderId(string $orderId)
    {
        $result = Transaction::findApprovedByOrderId($orderId);
        return $result[0] ?? null;
    }

    /**
     * Retrieve a transaction by buy order and session ID. Throws if not found.
     *
     * @param string $buyOrder
     * @param string $sessionId
     * @return mixed
     * @throws TokenNotFoundOnDatabaseException
     */
    public function getByBuyOrderAndSessionId(string $buyOrder, string $sessionId)
    {
        $result = Transaction::findByBuyOrderAndSessionId($buyOrder, $sessionId);
        if (!is_array($result) || count($result) <= 0) {
            throw new TokenNotFoundOnDatabaseException("BuyOrder '{$buyOrder}' y SessionId '{$sessionId}' no se encontró en la base de datos de transacciones, por lo que no se puede completar el proceso");
        }
        return $result[0];
    }

    /**
     * Check if the transaction table exists in the database.
     *
     * @return array
     */
    public function checkExistTable(): array
    {
        return Transaction::checkExistTable();
    }
}
