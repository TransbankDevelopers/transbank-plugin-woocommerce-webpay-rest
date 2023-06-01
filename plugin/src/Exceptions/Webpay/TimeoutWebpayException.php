<?php

namespace Transbank\WooCommerce\WebpayRest\Exceptions\Webpay;

class TimeoutWebpayException extends \Exception
{
    private $buyOrder;
    private $sessionId;
    private $transaction;

    public function __construct($message, $buyOrder, $sessionId, $transaction, $code = 0, \Exception $previous = null) {
        $this->buyOrder = $buyOrder;
        $this->sessionId = $sessionId;
        $this->transaction = $transaction;
        parent::__construct($message, $code, $previous);
    }

    public function getBuyOrder() {
        return $this->buyOrder;
    }

    public function getSessionId() {
        return $this->sessionId;
    }

    public function getTransaction() {
        return $this->transaction;
    }
}