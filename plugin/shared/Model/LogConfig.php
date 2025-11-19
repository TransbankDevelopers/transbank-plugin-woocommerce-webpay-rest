<?php

namespace Transbank\Plugin\Model;

class LogConfig  {
    public $logDir = null;

    private $maskingEnabled = false;

    public function __construct($logDir, $maskingEnabled) {
        $this->logDir = $logDir;
        $this->maskingEnabled = $maskingEnabled;
    }

    public function getLogDir()
    {
        return $this->logDir;
    }

    public function setLogDir($logDir)
    {
        $this->logDir = $logDir;
    }

    public function maskingEnabled()
    {
        return $this->maskingEnabled;
    }
}
