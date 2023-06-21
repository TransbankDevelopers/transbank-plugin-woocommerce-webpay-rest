<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

class ErrorUtil {

    /*
    status 401 => "error_message": "Not Authorized"
    status 422 => "error_message": "Api mismatch error, required version is 1.2"
    status 422 => "error_message": "Invalid value for parameter: token"
    status 422 => "error_message": "The transactions's date has passed max time (7 days) to recover the status"
    status 422 => "error_message": "Invalid value for parameter: transaction not found"
    */

    public static function isApiMismatchError(\Throwable $e)
    {
        $error = $e->getMessage();
        $position = strpos($error, 'Api mismatch error');
        return $position !== false;
    }

    public static function getVersionFromApiMismatchError(\Throwable $e)
    {
        /* Estraemos la version del error 'Api mismatch error, required version is 1.3' */
        if (ErrorUtil::isApiMismatchError($e)){
            $pattern = '/\d+\.\d+/';
            if (preg_match($pattern, $e->getMessage(), $matches)) {
                return $matches[0];
            }
        }
        return null;
    }

    public static function isNotAuthorizedError(\Throwable $e)
    {
        $error = $e->getMessage();
        $position = strpos($error, 'Not Authorized');
        return $position !== false;
    }

    public static function isMaxTimeError(\Throwable $e)
    {
        $error = $e->getMessage();
        $position = strpos($error, 'date has passed max time');
        return $position !== false;
    }
}

