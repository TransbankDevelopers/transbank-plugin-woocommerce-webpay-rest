<?php

namespace Transbank\Plugin\Exceptions\Webpay;

use Transbank\Plugin\Exceptions\BaseException;

class RejectedCommitWebpayException extends BaseException
{
    private $tokenWs;
    private $transaction;
    private $commitResponse;

    public function __construct($message, $tokenWs, $transaction, $commitResponse,
     \Exception $previous = null) {
        $this->tokenWs = $tokenWs;
        $this->transaction = $transaction;
        $this->commitResponse = $commitResponse;
        parent::__construct($message, $previous);
    }

    public function getTokenWs() {
        return $this->tokenWs;
    }

    public function getTransaction() {
        return $this->transaction;
    }

    public function getCommitResponse() {
        return $this->commitResponse;
    }
}
