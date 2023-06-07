<?php

namespace Transbank\WooCommerce\WebpayRest\Exceptions\Oneclick;

class RejectedInscriptionOneclickException extends \Exception
{
    private $tbkToken;
    private $inscription;
    private $finishInscriptionResponse;

    public function __construct($message, $tbkToken, $inscription, $finishInscriptionResponse, $code = 0, \Exception $previous = null) {
        $this->tbkToken = $tbkToken;
        $this->inscription = $inscription;
        $this->finishInscriptionResponse = $finishInscriptionResponse;
        parent::__construct($message, $code, $previous);
    }

    public function getTbkToken() {
        return $this->tbkToken;
    }

    public function getInscription() {
        return $this->inscription;
    }

    public function getFinishInscriptionResponse() {
        return $this->finishInscriptionResponse;
    }
}
