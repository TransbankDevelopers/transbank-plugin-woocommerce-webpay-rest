<?php

namespace Transbank\Plugin\Exceptions;

class BaseException extends \Exception
{
    const DEFAULT_CODE = 0;
    public function __construct($message, \Throwable $previous = null)
    {
        parent::__construct($message, self::DEFAULT_CODE, $previous);
    }
}
