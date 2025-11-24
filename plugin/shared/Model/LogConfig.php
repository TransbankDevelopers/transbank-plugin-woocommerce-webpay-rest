<?php

namespace Transbank\Plugin\Model;

class LogConfig  {
    public $logDir = null;

    private $isMaskingEnabled = false;

    public function __construct($logDir, $isMaskingEnabled) {
        $this->logDir = $logDir;
        $this->isMaskingEnabled = $isMaskingEnabled;
    }

    public function getLogDir()
    {
        return $this->logDir;
    }

    public function setLogDir($logDir)
    {
        $this->logDir = $logDir;
    }

    public function isMaskingEnabled()
    {
        return $this->isMaskingEnabled;
    }
}
