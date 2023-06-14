<?php

namespace Transbank\WooCommerce\WebpayRest\Exceptions\Webpay;

class NotFoundTransactionWebpayException extends \Exception
{
    private $orderId;

    public function __construct($message, $orderId, $code = 0, \Exception $previous = null) {
        $this->orderId = $orderId;
        parent::__construct($message, $code, $previous);
    }

    public function getOrderId() {
        return $this->orderId;
    }

}
