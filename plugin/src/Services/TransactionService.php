<?php

namespace Transbank\WooCommerce\WebpayRest\Services;

use Transbank\WooCommerce\WebpayRest\Repositories\TransactionRepository;
use Transbank\Plugin\Helpers\TbkConstants;
use Transbank\Plugin\Model\TbkTransaction;
use Transbank\Webpay\Oneclick\Responses\MallTransactionAuthorizeResponse;
use Transbank\Webpay\WebpayPlus\Responses\TransactionRefundResponse;
use Transbank\Webpay\Oneclick\Responses\MallTransactionRefundResponse;
use Transbank\Plugin\Exceptions\DatabaseRecordCreationException;
use Transbank\Plugin\Exceptions\RecordNotFoundOnDatabaseException;

class TransactionService
{
    private TransactionRepository $repository;


    public function __construct(
        TransactionRepository $repository
    ) {
        $this->repository = $repository;
    }

    /**
     * Create a transaction record.
     *
     * @param TbkTransaction $data Transaction data to be stored.
     * @return mixed
     */
    public function createAndGet(TbkTransaction $data): TbkTransaction
    {
        if ($data->getProduct() === TbkConstants::TRANSACTION_WEBPAY_PLUS) {
            $data->setChildBuyOrder('');
            $data->setChildCommerceCode('');
        } else {
            $data->setToken('');
            $data->setSessionId('');
        }
        try {
            $id = $this->repository->insert($data);

            $record = $this->repository->findById($id);
            if (!$record) {
                throw new RecordNotFoundOnDatabaseException('Transacción no encontrada');
            }

            return new TbkTransaction($record);
        } catch (\Exception) {
            throw new DatabaseRecordCreationException("Problemas al crear el registro de Transacción");
        }
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
        $this->repository->update($transactionId, $data);
    }

    /**
     * Retrieve the first transaction by token.
     *
     * @param mixed $token
     * @return object|null
     */
    public function findFirstByToken(string $token): ?object
    {
        return $this->repository->findByToken($token);
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
     * Retrieve the first transaction by orderId.
     *
     * @param string $orderId
     * @return object|null
     */
    public function findFirstByOrderId(string $orderId): ?object
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
        $result = $this->repository->findByToken($token);
        if (is_null($result)) {
            return false;
        }
        return $result->status != TbkConstants::TRANSACTION_STATUS_INITIALIZED;
    }

    public function updateWithRefundResponse(string $transactionId, TransactionRefundResponse|MallTransactionRefundResponse $resp): void
    {
        $this->update($transactionId, [
            'last_refund_type' => $resp->getType(),
            'last_refund_response' => json_encode($resp)
        ]);
    }

    public function updateWithRefundResponseError(string $transactionId, string $detailError): void
    {
        $this->update($transactionId, [
            'error' => 'Refund error',
            'detail_error' => $detailError
        ]);
    }

    public function updateWithAuthorizeResponse(string $transactionId, MallTransactionAuthorizeResponse $resp): void
    {
        $this->update($transactionId, [
            'status' => TbkConstants::TRANSACTION_STATUS_APPROVED,
            'transbank_status' => $resp->getDetails()[0]->getStatus() ?? null,
            'transbank_response' => json_encode($resp),
        ]);
    }

    public function updateWithAuthorizeResponseError(string $transactionId, string $error, string $detailError): void
    {
        $this->update($transactionId, [
            'status' => TbkConstants::TRANSACTION_STATUS_FAILED,
            'error' => $error,
            'detail_error' => $detailError
        ]);
    }
}
