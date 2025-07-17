<?php

namespace Transbank\Plugin\Exceptions\Oneclick;

use Transbank\Plugin\Exceptions\BaseException;

class FinishOneclickException extends BaseException
{
    private $tbkToken;

    public function __construct($message, $tbkToken, \Exception $previous = null) {
        $this->tbkToken = $tbkToken;
        parent::__construct($message, $previous);
    }

    public function getTbkToken() {
        return $this->tbkToken;
    }

}
