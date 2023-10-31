<?php

namespace Transbank\Plugin\Exceptions\Webpay;

use Transbank\Plugin\Exceptions\BaseException;

class UserCancelWebpayException extends BaseException
{
    private $tbkToken;
    private $transaction;

    public function __construct($message, $tbkToken, $transaction,
     \Exception $previous = null) {
        $this->tbkToken = $tbkToken;
        $this->transaction = $transaction;
        parent::__construct($message, $previous);
    }

    public function getTbkToken() {
        return $this->tbkToken;
    }

    public function getTransaction() {
        return $this->transaction;
    }
}
