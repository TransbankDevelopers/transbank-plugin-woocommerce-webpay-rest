<?php

namespace Transbank\Plugin\Helpers;

interface ILogger {
    function logInfo($str);
    function logError($str);
    function logDebug($str);
    function getInfo();
    function getLogDetail($filename);
}
