<?php

namespace Transbank\Plugin\Exceptions\Webpay;

use Transbank\Plugin\Exceptions\BaseException;

class RefundWebpayException extends BaseException
{
    private $token;
    private $transaction;

    public function __construct($message, $token, $transaction, \Exception $previous = null) {
        $this->token = $token;
        $this->transaction = $transaction;
        parent::__construct($message, $previous);
    }

    public function getToken() {
        return $this->token;
    }

    public function getTransaction() {
        return $this->transaction;
    }
}
