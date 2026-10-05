<?php

namespace Transbank\Plugin\Exceptions\Oneclick;

use Transbank\Plugin\Exceptions\BaseException;

class OwnerMismatchInscriptionOneclickException extends BaseException
{
    public function __construct(\Exception $previous = null) {
        parent::__construct('La sesión actual no corresponde al usuario de la inscripción.', $previous);
    }
}
