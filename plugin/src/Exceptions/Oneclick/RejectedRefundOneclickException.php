<?php

namespace Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick;

class RejectedRefundOneclickException extends \Exception
{
    private $buyOrder;
    private $childBuyOrder;
    private $transaction;
    private $refundResponse;

    public function __construct($message, $buyOrder, $childBuyOrder, $transaction, $refundResponse, $code = 0, \Exception $previous = null) {
        $this->buyOrder = $buyOrder;
        $this->childBuyOrder = $childBuyOrder;
        $this->transaction = $transaction;
        $this->refundResponse = $refundResponse;
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

    public function getRefundResponse() {
        return $this->refundResponse;
    }
}
