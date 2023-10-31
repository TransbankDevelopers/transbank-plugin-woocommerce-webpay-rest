<?php

namespace Transbank\Plugin\Exceptions\Webpay;

class InvalidStatusWebpayException extends \Exception
{
    private $tokenWs;
    private $transaction;

    public function __construct($message, $tokenWs, $transaction, $code = 0, \Exception $previous = null) {
        $this->tokenWs = $tokenWs;
        $this->transaction = $transaction;
        parent::__construct($message, $code, $previous);
    }

    public function getTokenWs() {
        return $this->tokenWs;
    }

    public function getTransaction() {
        return $this->transaction;
    }
}
