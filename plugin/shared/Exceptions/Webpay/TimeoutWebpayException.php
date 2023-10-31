<?php

namespace Transbank\Plugin\Exceptions\Webpay;

use Transbank\Plugin\Exceptions\BaseException;

class TimeoutWebpayException extends BaseException
{
    private $buyOrder;
    private $sessionId;
    private $transaction;

    public function __construct($message, $buyOrder, $sessionId, $transaction,
     \Exception $previous = null) {
        $this->buyOrder = $buyOrder;
        $this->sessionId = $sessionId;
        $this->transaction = $transaction;
        parent::__construct($message, $previous);
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
