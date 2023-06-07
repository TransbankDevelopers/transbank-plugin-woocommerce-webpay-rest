<?php

namespace Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick;

class ConstraintsViolatedAuthorizeOneclickException extends \Exception
{
    private $authorizeResponse;

    public function __construct($message, $authorizeResponse, $code = 0, \Exception $previous = null) {
        $this->authorizeResponse = $authorizeResponse;
        parent::__construct($message, $code, $previous);
    }

    public function getAuthorizeResponse() {
        return $this->authorizeResponse;
    }
}