<?php

namespace Transbank\WooCommerce\WebpayRest\Repositories;

use Transbank\Plugin\Repositories\TransbankExecutionErrorLogRepositoryInterface;
use Transbank\WooCommerce\WebpayRest\Models\TransbankExecutionErrorLog;

class TransbankExecutionErrorLogRepository implements TransbankExecutionErrorLogRepositoryInterface
{
    /**
     * Get the name of the error log database table.
     *
     * @return string The name of the table used to store error logs.
     */
    public function getTableName(): string
    {
        return TransbankExecutionErrorLog::getTableName();
    }
    public function create($orderId, $service, $product, $enviroment, $commerceCode, $data, $error, $originalError, $customError)
    {
        return TransbankExecutionErrorLog::create($orderId, $service, $product, $enviroment, $commerceCode, $data, $error, $originalError, $customError);
    }
    
}

