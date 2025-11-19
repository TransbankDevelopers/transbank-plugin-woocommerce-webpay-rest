<?php

namespace Transbank\Plugin\Helpers;

interface ILogger {
    function logInfo(string $msg, array $context = []);
    function logError(string $msg, array $context = []);
    function logDebug(string $msg, array $context = []);
    function getInfo();
    function getLogDetail($filename);
}

