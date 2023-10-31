<?php

namespace Transbank\Plugin\Exceptions\Oneclick;

use Transbank\Plugin\Exceptions\BaseException;

class GetTransactionOneclickException extends BaseException
{
    private $orderId;

    public function __construct($message, $orderId, \Exception $previous = null) {
        $this->orderId = $orderId;
        parent::__construct($message, $previous);
    }

    public function getOrderId() {
        return $this->orderId;
    }

}
