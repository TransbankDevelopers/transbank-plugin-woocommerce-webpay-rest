<?php

namespace Transbank\Plugin\Helpers;

class ArrayUtils
{
    public static function getValue($array, $name, $defaulValue = null){
        return isset($array[$name]) ? $array[$name] : $defaulValue;
    }
}
