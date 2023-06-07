<?php

namespace Transbank\WooCommerce\WebpayRest\Exceptions\Webpay;

class DoubleTokenWebpayException extends \Exception
{
    private $tbkToken1;
    private $tbkToken2;
    private $transaction;

    public function __construct($message, $tbkToken1, $tbkToken2, $transaction, $code = 0, \Exception $previous = null) {
        $this->tbkToken1 = $tbkToken1;
        $this->tbkToken2 = $tbkToken2;
        $this->transaction = $transaction;
        parent::__construct($message, $code, $previous);
    }

    public function getTbkToken1() {
        return $this->tbkToken1;
    }

    public function getTbkToken2() {
        return $this->tbkToken2;
    }

    public function getTransaction() {
        return $this->transaction;
    }
}