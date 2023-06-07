<?php

namespace Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick;

class UserCancelInscriptionOneclickException extends \Exception
{
    private $tbkToken;
    private $inscription;

    public function __construct($message, $tbkToken, $inscription, $code = 0, \Exception $previous = null) {
        $this->tbkToken = $tbkToken;
        $this->inscription = $inscription;
        parent::__construct($message, $code, $previous);
    }

    public function getTbkToken() {
        return $this->tbkToken;
    }

    public function getInscription() {
        return $this->inscription;
    }
}
