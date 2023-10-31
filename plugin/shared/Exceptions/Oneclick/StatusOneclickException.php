<?php

namespace Transbank\Plugin\Exceptions\Oneclick;

use Transbank\Plugin\Exceptions\BaseException;

class StatusOneclickException extends BaseException
{
    private $buyOrder;

    public function __construct($message, $buyOrder, \Exception $previous = null) {
        $this->buyOrder = $buyOrder;
        parent::__construct($message, $previous);
    }

    public function getBuyOrder() {
        return $this->buyOrder;
    }

}
