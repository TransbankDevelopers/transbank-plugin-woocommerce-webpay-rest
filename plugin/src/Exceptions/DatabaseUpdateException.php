<?php

namespace Transbank\WooCommerce\WebpayRest\Exceptions;

use Exception;

class DatabaseUpdateException extends Exception
{
    public function __construct(string $message = "Error al actualizar en la base de datos", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
