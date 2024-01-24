<?php

namespace Transbank\WooCommerce\WebpayRest\Models;

class TransbankApiServiceLog extends BaseModel
{
    const TABLE_NAME = 'transbank_api_service_log';

    /**
     * @return string
     */
    public static function getTableName(): string
    {
        return static::getBaseTableName(static::TABLE_NAME);
    }

    public static function create($orderId, $service, $product, $enviroment, $commerceCode, $input, $response)
    {
        return static::insertBase(static::getTableName(), [
            'order_id'         => $orderId,
            'service'          => $service,
            'product'          => $product,
            'enviroment'       => $enviroment,
            'commerce_code'    => $commerceCode,
            'input'            => $input,
            'response'         => $response
        ]);
    }

    public static function createError($orderId, $service, $product, $enviroment, $commerceCode, $input, $error, $originalError, $customError)
    {
        return static::insertBase(static::getTableName(), [
            'order_id'         => $orderId,
            'service'          => $service,
            'product'          => $product,
            'enviroment'       => $enviroment,
            'commerce_code'    => $commerceCode,
            'input'            => $input,
            'error'            => $error,
            'original_error'   => $originalError,
            'custom_error'     => $customError
        ]);
    }

}

