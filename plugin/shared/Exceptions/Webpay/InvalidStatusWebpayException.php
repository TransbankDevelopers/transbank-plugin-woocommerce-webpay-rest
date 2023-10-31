<?php

namespace Transbank\Plugin\Exceptions\Webpay;

use Transbank\Plugin\Exceptions\BaseException;

class InvalidStatusWebpayException extends BaseException
{
    private $tokenWs;
    private $transaction;

    public function __construct($message, $tokenWs, $transaction, \Exception $previous = null) {
        $this->tokenWs = $tokenWs;
        $this->transaction = $transaction;
        parent::__construct($message, $previous);
    }

    public function getTokenWs() {
        return $this->tokenWs;
    }

    public function getTransaction() {
        return $this->transaction;
    }
}
