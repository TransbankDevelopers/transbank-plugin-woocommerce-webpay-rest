<?php

namespace Transbank\Plugin\Exceptions\Oneclick;

use Transbank\Plugin\Exceptions\BaseException;

class RejectedRefundOneclickException extends BaseException
{
    private $buyOrder;
    private $childBuyOrder;
    private $transaction;
    private $refundResponse;

    public function __construct($message, $buyOrder, $childBuyOrder, $transaction, $refundResponse,
     \Exception $previous = null) {
        $this->buyOrder = $buyOrder;
        $this->childBuyOrder = $childBuyOrder;
        $this->transaction = $transaction;
        $this->refundResponse = $refundResponse;
        parent::__construct($message, $previous);
    }

    public function getBuyOrder() {
        return $this->buyOrder;
    }

    public function getChildBuyOrder() {
        return $this->childBuyOrder;
    }

    public function getTransaction() {
        return $this->transaction;
    }

    public function getRefundResponse() {
        return $this->refundResponse;
    }
}
