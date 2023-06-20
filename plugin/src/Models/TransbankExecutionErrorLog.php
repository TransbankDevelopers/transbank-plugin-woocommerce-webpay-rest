<?php

namespace Transbank\WooCommerce\WebpayRest\Models;

use function is_multisite;

class TransbankExecutionErrorLog
{
    const TABLE_NAME = 'transbank_execution_error_log';

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

    public static function create($orderId, $service, $product, $enviroment, $commerceCode, $input, $error, $originalError, $customError)
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
