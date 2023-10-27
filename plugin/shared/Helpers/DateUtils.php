<?php

namespace Transbank\Plugin\Helpers;

class DateUtils
{
    public static function getNow(){
        return date("Y-m-d H:i:s");
    }
}
