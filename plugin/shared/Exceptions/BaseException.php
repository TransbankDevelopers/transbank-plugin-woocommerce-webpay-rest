<?php

namespace Transbank\Plugin\Exceptions;

use Transbank\Plugin\Helpers\ExceptionConstants;

class BaseException extends \Exception
{
    public function __construct($message, \Exception $previous = null) {
        parent::__construct($message, ExceptionConstants::DEFAULT_CODE, $previous);
    }
}
