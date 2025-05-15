<?php

namespace Transbank\WooCommerce\WebpayRest\Repositories;

use Transbank\Plugin\Repositories\TransbankApiServiceLogRepositoryInterface;
use Transbank\WooCommerce\WebpayRest\Models\TransbankApiServiceLog;

class TransbankApiServiceLogRepository implements TransbankApiServiceLogRepositoryInterface
{
    /**
     * Get the name of the api service log database table.
     *
     * @return string The name of the table used to store api service logs.
     */
    public function getTableName(): string
    {
        return TransbankApiServiceLog::getTableName();
    }
    public function create($orderId, $service, $product, $enviroment, $commerceCode, $input, $response)
    {
        return TransbankApiServiceLog::create($orderId, $service, $product, $enviroment, $commerceCode, $input, $response);
    }

    public function createError($orderId, $service, $product, $enviroment, $commerceCode, $input, $error, $originalError, $customError)
    {
        return TransbankApiServiceLog::createError($orderId, $service, $product, $enviroment, $commerceCode, $input, $error, $originalError, $customError);
    }
}
