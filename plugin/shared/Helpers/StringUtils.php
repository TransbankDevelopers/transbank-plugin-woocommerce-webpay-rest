<?php

namespace Transbank\Plugin\Helpers;

class StringUtils
{
    public static function isBlankOrNull($str){
        return !isset($str) || trim($str) === '';
    }

    public static function isNotBlankOrNull($str){
        return !StringUtils::isBlankOrNull($str);
    }

    public static function hasLength($str, $length){
        if (strlen($str) == $length){
            return true;
        }
        return false;
    }

    public static function snakeToCamel($str) {
        return lcfirst(str_replace('_', '', ucwords($str, '_')));
    }

    public static function camelToSnake($str) {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $str));
    }
}
