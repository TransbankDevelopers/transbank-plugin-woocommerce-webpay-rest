<?php

namespace Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick;

class StatusOneclickException extends \Exception
{
    private $buyOrder;

    public function __construct($message, $buyOrder, $code = 0, \Exception $previous = null) {
        $this->buyOrder = $buyOrder;
        parent::__construct($message, $code, $previous);
    }

    public function getBuyOrder() {
        return $this->buyOrder;
    }

}
