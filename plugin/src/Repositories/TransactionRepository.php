<?php

namespace Transbank\WooCommerce\WebpayRest\Repositories;

use Transbank\Plugin\Helpers\TbkConstants;
use Transbank\Plugin\Model\TbkTransaction;
use Transbank\Plugin\Repositories\TransactionRepositoryInterface;
use Transbank\Plugin\Exceptions\RecordNotFoundOnDatabaseException;

class TransactionRepository extends BaseRepository implements TransactionRepositoryInterface
{
    const TRANSACTIONS_TABLE_NAME = 'webpay_rest_transactions';

    /**
     * Get the name of the transaction database table.
     *
     * @return string The name of the table used to store transactions.
     */
    public function getTableName(): string
    {
        return $this->getBaseTableName(static::TRANSACTIONS_TABLE_NAME);
    }

    /**
     * Create a transaction record.
     *
     * @param TbkTransaction $data Transaction data to be stored.
     * @return mixed
     */
    public function create(TbkTransaction $data)
    {
        $transaction = [
                'order_id'    => $data->getOrderId(),
                'buy_order'   => $data->getBuyOrder(),
                'amount'      => $data->getAmount(),
                'environment' => $data->getEnvironment(),
                'commerce_code'  => $data->getCommerceCode(),
                'product'     => $data->getProduct(),
                'status'      => $data->getStatus(),

                'token'       => $data->getToken(),
                'session_id'  => $data->getSessionId(),

                'child_buy_order' => $data->getChildBuyOrder(),
                'child_commerce_code' => $data->getChildCommerceCode()
            ];
        return $this->insertBase($transaction);
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
        return $this->updateBase($transactionId, $data);
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
        $result = $this->findFirstByToken($token);
        if (is_null($result)) {
            throw new RecordNotFoundOnDatabaseException(
                "Token no se encontró en la base de datos de transacciones");
        }
        return $result;
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
        $transactionTable = $this->getTableName();
        $result = $this->executeQuery(
            "SELECT * FROM $transactionTable WHERE buy_order = '%s'",
            $buyOrder
        );
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
        $transactionTable = $this->getTableName();
        $statusApproved = TbkConstants::TRANSACTION_STATUS_APPROVED;
        return $this->findFirst(
            "SELECT * FROM $transactionTable WHERE status = '$statusApproved' AND order_id = '%s'",
            $orderId
        );
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
        $transactionTable = $this->getTableName();
        $result = $this->executeQuery(
            "SELECT * FROM $transactionTable WHERE session_id = '%s' && buy_order='%s'",
            $sessionId,
            $buyOrder
        );
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
        return $this->getTransactionsByConditions(['order_id' => $orderId], 'id')[0] ?? null;
    }

    public function findFirstByToken($token): ?object
    {
        $transactionTable = $this->getTableName();
        return $this->findFirst(
            "SELECT * FROM $transactionTable WHERE `token` = '%s'",
            $token
        );
    }

}
