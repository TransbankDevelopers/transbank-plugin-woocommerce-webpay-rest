<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

class ErrorHelper
{
    public static function isGuzzleError(\Throwable $e)
    {
        return strpos($e->getMessage(), 'choose_handler');
    }

    public static function getGuzzleError()
    {
        return 'Error: otro plugin instalado en este sitio está provocando errores con la librería Guzzle.
            Si eres el administrador, intenta desactivar otros plugins hasta que ya no se muestre este error,
            para así reconocer cual está generando el problema. También puedes instalar una versión alternativa del
            plugin de Transbank que usa otra versión de Guzzle, lo que podría resolver el problema: Entra a
            https://github.com/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/releases/latest y descarga
            el archivo transbank-webpay-plus-rest-guzzle7.zip, desactiva el plugin de Transbank y sube este nuevo plugin
            como archivo zip. Si te pide reemplazar el que ya tienes, selecciona que si. ';
    }

    public static function getErrorMessageBasedOnTransbankSdkException(\Throwable $e)
    {
        if (strpos($e->getMessage(), 'choose_handler') !== false) {
            return ErrorHelper::getGuzzleError();
        }

        return 'Error: '.$e->getMessage();
    }
}
