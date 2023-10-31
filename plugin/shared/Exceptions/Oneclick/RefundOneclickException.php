<?php

namespace Transbank\Plugin\Exceptions\Oneclick;

class RefundOneclickException extends \Exception
{
    private $buyOrder;
    private $childBuyOrder;
    private $transaction;

    public function __construct($message, $buyOrder, $childBuyOrder, $transaction, $code = 0, \Exception $previous = null) {
        $this->buyOrder = $buyOrder;
        $this->childBuyOrder = $childBuyOrder;
        $this->transaction = $transaction;
        parent::__construct($message, $code, $previous);
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
}
