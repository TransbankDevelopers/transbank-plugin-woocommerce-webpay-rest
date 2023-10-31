<?php

namespace Transbank\Plugin\Exceptions\Oneclick;

use Transbank\Plugin\Exceptions\BaseException;

class RefundOneclickException extends BaseException
{
    private $buyOrder;
    private $childBuyOrder;
    private $transaction;

    public function __construct($message, $buyOrder, $childBuyOrder, $transaction,
        \Exception $previous = null) {
        $this->buyOrder = $buyOrder;
        $this->childBuyOrder = $childBuyOrder;
        $this->transaction = $transaction;
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
}
