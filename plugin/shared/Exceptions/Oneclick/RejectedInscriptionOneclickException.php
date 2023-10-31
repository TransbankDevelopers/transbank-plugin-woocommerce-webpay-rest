<?php

namespace Transbank\Plugin\Exceptions\Oneclick;

use Transbank\Plugin\Exceptions\BaseException;

class RejectedInscriptionOneclickException extends BaseException
{
    private $tbkToken;
    private $inscription;
    private $finishInscriptionResponse;

    public function __construct($message, $tbkToken, $inscription, $finishInscriptionResponse,
        \Exception $previous = null) {
        $this->tbkToken = $tbkToken;
        $this->inscription = $inscription;
        $this->finishInscriptionResponse = $finishInscriptionResponse;
        parent::__construct($message, $previous);
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
