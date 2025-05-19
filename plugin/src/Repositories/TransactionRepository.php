<?php

namespace Transbank\WooCommerce\WebpayRest\Repositories;

use Transbank\Plugin\Repositories\TransactionRepositoryInterface;
use Transbank\WooCommerce\WebpayRest\Models\Transaction;
use Transbank\Plugin\Exceptions\RecordNotFoundOnDatabaseException;

class TransactionRepository implements TransactionRepositoryInterface
{

    /**
     * Get the name of the transaction database table.
     *
     * @return string The name of the table used to store transactions.
     */
    public function getTableName(): string
    {
        return Transaction::getTableName();
    }

    /**
     * Create a transaction record.
     *
     * @param array $data Transaction data to be stored.
     * @return mixed
     */
    public function create(array $data)
    {
        return Transaction::create($data);
    }

    /**
     * Update an existing transaction record by id.
     *
     * @param string $transactionId Token identifying the transaction.
     * @param array $data New data to update the transaction with.
     * @return mixed
     */
    public function update(string $transactionId, array $data)
    {
        return Transaction::update($transactionId, $data);
    }

     /**
     * Retrieve a transaction by token. Throws an exception if not found.
     *
     * @param string $token The transaction token.
     * @return mixed
     * @throws RecordNotFoundOnDatabaseException
     */
    public function getByToken(string $token)
    {
        $result = Transaction::findByToken($token);
        if (!is_array($result) || empty($result)) {
            throw new RecordNotFoundOnDatabaseException(
                "Token no se encontró en la base de datos de transacciones");
        }
        return $result[0];
    }

     /**
     * Retrieve a transaction by buyOrder. Throws an exception if not found.
     *
     * @param string $buyOrder The buy order associated with the transaction.
     * @return mixed
     * @throws RecordNotFoundOnDatabaseException
     */
    public function getByBuyOrder(string $buyOrder)
    {
        $result = Transaction::findByBuyOrder($buyOrder);
        if (!is_array($result) || empty($result)) {
            throw new RecordNotFoundOnDatabaseException(
                "BuyOrder no se encontró en la base de datos de transacciones");
        }
        return $result[0];
    }

    /**
     * Retrieve the first approved transaction by orderId.
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
     * Retrieve a transaction by buyOrder and sessionId. Throws if not found.
     *
     * @param string $buyOrder
     * @param string $sessionId
     * @return mixed
     * @throws RecordNotFoundOnDatabaseException
     */
    public function getByBuyOrderAndSessionId(string $buyOrder, string $sessionId)
    {
        $result = Transaction::findByBuyOrderAndSessionId($buyOrder, $sessionId);
        if (!is_array($result) || empty($result)) {
            throw new RecordNotFoundOnDatabaseException(
                "BuyOrder '{$buyOrder}' y SessionId '{$sessionId}' no se encontró en la base de datos de transacciones, por lo que no se puede completar el proceso");
        }
        return $result[0];
    }

    /**
     * Retrieve the first transaction by orderId.
     *
     * @param mixed $orderId
     * @return object|null
     */
    public function findFirstByOrderId($orderId): ?object
    {
        return Transaction::findFirstByOrderId($orderId);
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
