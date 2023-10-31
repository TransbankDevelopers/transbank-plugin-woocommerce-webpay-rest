<?php

namespace Transbank\Plugin\Exceptions\Webpay;

class StatusWebpayException extends \Exception
{
    private $token;

    public function __construct($message, $token, $code = 0, \Exception $previous = null) {
        $this->token = $token;
        parent::__construct($message, $code, $previous);
    }

    public function getToken() {
        return $this->token;
    }

}
