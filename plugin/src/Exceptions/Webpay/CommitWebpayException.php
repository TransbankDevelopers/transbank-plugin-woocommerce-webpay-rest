<?php

namespace Transbank\WooCommerce\WebpayRest\Exceptions\Webpay;

class CommitWebpayException extends \Exception
{
    private $tbkToken;
    private $transaction;

    public function __construct($message, $tbkToken, $transaction, $code = 0, \Exception $previous = null) {
        $this->tbkToken = $tbkToken;
        $this->transaction = $transaction;
        parent::__construct($message, $code, $previous);
    }

    public function getTbkToken() {
        return $this->tbkToken;
    }

    public function getTransaction() {
        return $this->transaction;
    }
}
