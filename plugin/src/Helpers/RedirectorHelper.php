<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

class RedirectorHelper
{
    /**
     * Generar pago en Transbank.
     **/
    public static function getRedirectForm($url, array $data)
    {
        $msg = "<form action='".$url."' method='POST' name='webpayForm'>";
        foreach ($data as $name => $value) {
            $msg .= "<input type='hidden' name='".htmlentities($name)."' value='".htmlentities($value)."'>";
        }
        $msg .= '</form>'.'<script>'.'document.webpayForm.submit();'.'</script>';

        return $msg;
    }

    public static function redirect($url, array $data)
    {
        echo static::getRedirectForm($url, $data);
    }
}
