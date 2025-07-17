<?php

namespace Transbank\Plugin\Exceptions\Oneclick;

use Transbank\Plugin\Exceptions\BaseException;

class RefundOneclickException extends BaseException
{
    private $buyOrder;
    private $childBuyOrder;

    public function __construct($message, $buyOrder, $childBuyOrder,
        \Exception $previous = null) {
        $this->buyOrder = $buyOrder;
        $this->childBuyOrder = $childBuyOrder;
        parent::__construct($message, $previous);
    }

    public function getBuyOrder() {
        return $this->buyOrder;
    }

    public function getChildBuyOrder() {
        return $this->childBuyOrder;
    }
}
