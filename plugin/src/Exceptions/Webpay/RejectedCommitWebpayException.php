<?php

namespace Transbank\WooCommerce\WebpayRest\Exceptions\Webpay;

class RejectedCommitWebpayException extends \Exception
{
    private $tokenWs;
    private $transaction;
    private $commitResponse;

    public function __construct($message, $tokenWs, $transaction, $commitResponse, $code = 0, \Exception $previous = null) {
        $this->tokenWs = $tokenWs;
        $this->transaction = $transaction;
        $this->commitResponse = $commitResponse;
        parent::__construct($message, $code, $previous);
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
