<?php

namespace Transbank\WooCommerce\WebpayRest\Models;

class TransbankExecutionErrorLog extends BaseModel
{
    const TABLE_NAME = 'transbank_execution_error_log';

    /**
     * @return string
     */
    public static function getTableName(): string
    {
        return static::getBaseTableName(static::TABLE_NAME);
    }

    public static function create($orderId, $service, $product, $enviroment, $commerceCode, $data, $error, $originalError, $customError)
    {
        return static::insertBase(static::getTableName(), [
            'order_id'         => $orderId,
            'service'          => $service,
            'product'          => $product,
            'enviroment'       => $enviroment,
            'commerce_code'    => $commerceCode,
            'data'             => $data,
            'error'            => $error,
            'original_error'   => $originalError,
            'custom_error'     => $customError
        ]);
    }

}
