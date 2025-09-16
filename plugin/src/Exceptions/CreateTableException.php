<?php

namespace Transbank\WooCommerce\WebpayRest\Exceptions;

use Exception;

class CreateTableException extends Exception
{
    public function __construct(string $message = "Error al crear la tabla en la base de datos", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
