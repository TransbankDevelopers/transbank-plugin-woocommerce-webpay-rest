<?php

namespace Transbank\WooCommerce\WebpayRest\Repositories;

use Transbank\Plugin\Helpers\TbkConstants;
use Transbank\Plugin\Model\TbkTransaction;
use Transbank\Plugin\Exceptions\RecordNotFoundOnDatabaseException;

use Transbank\WooCommerce\WebpayRest\Infrastructure\Database\WpdbTableGateway;
use Transbank\WooCommerce\WebpayRest\Exceptions\DatabaseInsertException;

class TransactionRepository
{
    const TABLE_NAME = 'webpay_rest_transactions';


    private WpdbTableGateway $db;

    public function __construct(WpdbTableGateway $wpdb)
    {
        $this->db = $wpdb;
    }

    /**
     * Get the name of the transaction database table.
     *
     * @return string The name of the table used to store transactions.
     */
    public function getTableName(): string
    {
        return $this->db->getTableName();
    }


    /**
     * Get the name of the transaction without prefix database table.
     *
     * @return string The name of the table used to store transactions.
     */
    public function getRawTableName(): string
    {
        return self::TABLE_NAME;
    }


    /**
     * Create a transaction record and return id.
     *
     * @param TbkTransaction $data Transaction data to be stored.
     * @throws DatabaseInsertException
     * @return int
     */
    public function insert(TbkTransaction $data): int
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


        return $this->db->insert($transaction);
    }

    /**
     * Update an existing transaction record by id.
     *
     * @param string $transactionId Token identifying the transaction.
     * @param array $data New data to update the transaction with.
     *
     * @throws \Transbank\WooCommerce\WebpayRest\Exceptions\DatabaseUpdateException if update fails.
     */
    public function update(string $transactionId, array $data): void
    {
        $this->db->update(['id' => $transactionId], $data);
    }

    /**
     * Retrieve a transaction by token. Throws an exception if not found.
     *
     * @param string $token The transaction token.
     * @return object|null
     */
    public function findByToken(string $token): ?object
    {
        return $this->db->findOne(
            "SELECT * FROM `{$this->db->getTableName()}` WHERE `token` = %s",
            [$token],
        );
    }

    /**
     * Retrieve a transaction by buyOrder. Throws an exception if not found.
     *
     * @param string $buyOrder The buy order associated with the transaction.
     * @return object
     * @throws RecordNotFoundOnDatabaseException
     * @throws \InvalidArgumentException
     */
    public function getByBuyOrder(string $buyOrder): object
    {

        return $this->db->getOne(
            "SELECT * FROM `{$this->db->getTableName()}` WHERE `buy_order` = %s",
            [$buyOrder],
            'Registro no encontrado'
        );
    }

    /**
     * Retrieve the first approved transaction by orderId.
     *
     * @param string $orderId
     * @return object|null
     */
    public function findFirstApprovedByOrderId(string $orderId): ?object
    {
        $statusApproved = TbkConstants::TRANSACTION_STATUS_APPROVED;
        return $this->db->findOne(
            "SELECT * FROM `{$this->db->getTableName()}` WHERE `status` = %s AND `order_id` = %d",
            [
                $statusApproved,
                (int)$orderId,
            ],
        );
    }

    /**
     * Retrieve the first transaction by orderId.
     *
     * @param string $orderId
     * @return object|null
     */
    public function findFirstByOrderId(string $orderId): ?object
    {
        return $this->db->findOne(
            "SELECT * FROM `{$this->db->getTableName()}` WHERE `order_id` = %d ORDER BY id DESC",
            [
                (int)$orderId,
            ],
        );
    }

    /**
     * Retrieve a transaction by ID. return null if not found.
     *
     * @param int $id The transaction ID.
     * @return object|null transaction object
     */
    public function findById(int $id): ?object
    {
        return $this->db->findOne(
            "SELECT * FROM `{$this->db->getTableName()}` WHERE `id` = %d",
            [$id],
        );
    }
}
