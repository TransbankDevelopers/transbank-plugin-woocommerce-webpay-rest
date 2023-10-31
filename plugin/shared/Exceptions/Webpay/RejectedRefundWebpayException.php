<?php

namespace Transbank\Plugin\Exceptions\Webpay;

use Transbank\Plugin\Exceptions\BaseException;

class RejectedRefundWebpayException extends BaseException
{
    private $token;
    private $transaction;
    private $refundResponse;

    public function __construct($message, $token, $transaction, $refundResponse,
     \Exception $previous = null) {
        $this->token = $token;
        $this->transaction = $transaction;
        $this->refundResponse = $refundResponse;
        parent::__construct($message, $previous);
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
