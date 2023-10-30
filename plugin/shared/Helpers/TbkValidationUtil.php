<?php

namespace Transbank\Plugin\Helpers;

use Throwable;

/**
 * Esta clase tiene el propósito de identificar errores retornados por el api de Transbank
 * estos mensajes retornados podrían ser los siguientes:
 * status 401 => "error_message": "Not Authorized"
 * status 422 => "error_message": "Api mismatch error, required version is 1.2"
 * status 422 => "error_message": "Invalid value for parameter: token"
 * status 422 => "error_message":
 *            "The transactions's date has passed max time (7 days) to recover the status"
 * status 422 => "error_message": "Invalid value for parameter: transaction not found"
 *
 */
class TbkValidationUtil {

    /**
     * Este método recibe una excepción, y valida que sea del tipo:
     * status 422 => "error_message": "Api mismatch error, required version is 1.2"
     *
     * @param Throwable $e
     */
    public static function isApiMismatchError(Throwable $e)
    {
        $error = $e->getMessage();
        $position = strpos($error, 'Api mismatch error');
        return $position !== false;
    }

    /**
     * Este método recibe una excepción por uso de versión de api incorrecto
     * y extrae la versión del api usado.
     * El mensaje recibido tiene este formato 'Api mismatch error, required version is 1.3'
     *
     * @param Throwable $e
     */
    public static function getVersionFromApiMismatchError(Throwable $e)
    {
        if (TbkValidationUtil::isApiMismatchError($e)){
            $pattern = '/\d+\.\d+/';
            if (preg_match($pattern, $e->getMessage(), $matches)) {
                return $matches[0];
            }
        }
        return null;
    }

    /**
     * Este método recibe una excepción, y valida que sea del tipo:
     * status 401 => "error_message": "Not Authorized"
     *
     * @param Throwable $e
     */
    public static function isNotAuthorizedError(Throwable $e)
    {
        $error = $e->getMessage();
        $position = strpos($error, 'Not Authorized');
        return $position !== false;
    }

    /**
     * Este método recibe una excepción, y valida que sea del tipo:
     * status 422 => "error_message":
     * "The transactions's date has passed max time (7 days) to recover the status"
     *
     * @param Throwable $e
     */
    public static function isMaxTimeError(Throwable $e)
    {
        $error = $e->getMessage();
        $position = strpos($error, 'date has passed max time');
        return $position !== false;
    }

}
