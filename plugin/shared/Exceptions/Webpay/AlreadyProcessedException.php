<?php

namespace Transbank\Plugin\Exceptions\Webpay;

use Transbank\Plugin\Exceptions\BaseException;

class AlreadyProcessedException extends BaseException
{
    private $transaction;
    private $flow;

    public function __construct(
        $message,
        $transaction,
        $flow,
        \Exception $previous = null
    ) {
        $this->transaction = $transaction;
        $this->flow = $flow;
        parent::__construct($message, $previous);
    }

    public function getTransaction()
    {
        return $this->transaction;
    }

    public function getFlow()
    {
        return $this->flow;
    }
}
