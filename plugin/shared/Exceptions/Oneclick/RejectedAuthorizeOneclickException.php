<?php

namespace Transbank\Plugin\Exceptions\Oneclick;

use Transbank\Plugin\Exceptions\BaseException;

class RejectedAuthorizeOneclickException extends BaseException
{
    private $authorizeResponse;

    public function __construct($message, $authorizeResponse, \Exception $previous = null) {
        $this->authorizeResponse = $authorizeResponse;
        parent::__construct($message, $previous);
    }

    public function getAuthorizeResponse() {
        return $this->authorizeResponse;
    }
}
