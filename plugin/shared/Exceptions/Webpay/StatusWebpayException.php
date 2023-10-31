<?php

namespace Transbank\Plugin\Exceptions\Webpay;

use Transbank\Plugin\Exceptions\BaseException;

class StatusWebpayException extends BaseException
{
    private $token;

    public function __construct($message, $token, \Exception $previous = null) {
        $this->token = $token;
        parent::__construct($message, $previous);
    }

    public function getToken() {
        return $this->token;
    }

}
