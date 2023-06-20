<?php

namespace Transbank\WooCommerce\WebpayRest\Models;

class TransbankApiServiceLog
{
    const TABLE_NAME = 'transbank_api_service_log';

    /**
     * @return string
     */
    public static function getTableName(): string
    {
        global $wpdb;
        if (is_multisite()) {
            return $wpdb->base_prefix.static::TABLE_NAME;
        } else {
            return $wpdb->prefix.static::TABLE_NAME;
        }
    }

    public static function create($orderId, $service, $product, $enviroment, $commerceCode, $input, $response)
    {
        global $wpdb;

        return $wpdb->insert(static::getTableName(), [
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
        global $wpdb;

        return $wpdb->insert(static::getTableName(), [
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

