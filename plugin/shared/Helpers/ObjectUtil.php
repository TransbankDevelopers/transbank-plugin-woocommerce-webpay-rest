<?php

namespace Transbank\Plugin\Helpers;

class ObjectUtil
{
    public static function copyPropertiesFromTo($from, $to){
        foreach (get_object_vars($from) as $property => $value) {
            $to->$property = $value;
        }
        return $to;
    }

}
