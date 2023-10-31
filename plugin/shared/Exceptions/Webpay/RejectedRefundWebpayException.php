<?php

namespace Transbank\Plugin\Exceptions\Webpay;

class RejectedRefundWebpayException extends \Exception
{
    private $token;
    private $transaction;
    private $refundResponse;

    public function __construct($message, $token, $transaction, $refundResponse, $code = 0, \Exception $previous = null) {
        $this->token = $token;
        $this->transaction = $transaction;
        $this->refundResponse = $refundResponse;
        parent::__construct($message, $code, $previous);
    }

    public function getToken() {
        return $this->token;
    }

    public function getTransaction() {
        return $this->transaction;
    }

    public function getRefundResponse() {
        return $this->refundResponse;
    }
}
