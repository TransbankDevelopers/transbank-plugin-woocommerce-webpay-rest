<?php

namespace Transbank\Plugin\Exceptions\Oneclick;

use Transbank\Plugin\Exceptions\BaseException;

class TimeoutInscriptionOneclickException extends BaseException
{
    private $tbkToken;
    private $inscription;

    public function __construct($message, $tbkToken, $inscription, \Exception $previous = null) {
        $this->tbkToken = $tbkToken;
        $this->inscription = $inscription;
        parent::__construct($message, $previous);
    }

    public function getTbkToken() {
        return $this->tbkToken;
    }

    public function getInscription() {
        return $this->inscription;
    }
}
