<?php

namespace Transbank\Plugin\Exceptions\Webpay;

use Transbank\Plugin\Exceptions\BaseException;

class DoubleTokenWebpayException extends BaseException
{
    private $tbkToken1;
    private $tbkToken2;
    private $transaction;

    public function __construct($message, $tbkToken1, $tbkToken2, $transaction,
     \Exception $previous = null) {
        $this->tbkToken1 = $tbkToken1;
        $this->tbkToken2 = $tbkToken2;
        $this->transaction = $transaction;
        parent::__construct($message, $previous);
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
