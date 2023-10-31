<?php

namespace Transbank\Plugin\Helpers;

class InfoUtil
{
    /**
     * Este método valida la versión de PHP.
     *
     * @return array
     */
    public static function getValidatephp()
    {
        if (version_compare(phpversion(), '8.1.8', '<=') &&
                version_compare(phpversion(), '7.0.0', '>=')) {
            return [
                'status'  => 'OK',
                'version' => phpversion(),
            ];
        } else {
            return [
                'status'  => 'WARN: El plugin no ha sido testeado con esta version',
                'version' => phpversion(),
            ];
        }
    }

    /**
     * Este método comprueba que la extensión se encuentre instalada.
     *
     * @param string $extension Es el nombre de la extensión a validar.
     * @return array
     */
    public static function getCheckExtension($extension)
    {
        if (extension_loaded($extension)) {
            if ($extension == 'openssl') {
                $version = OPENSSL_VERSION_TEXT;
            } else {
                $version = phpversion($extension);
                if (empty($version) || $version == null
                    || $version === false || $version == ' ' || $version == '') {
                    $version = 'PHP Extension Compiled. ver:'.phpversion();
                }
            }
            $status = 'OK';
            $result = [
                'status'  => $status,
                'version' => $version,
            ];
        } else {
            $result = [
                'status'  => 'Error!',
                'version' => 'No Disponible',
            ];
        }

        return $result;
    }

    /**
     * Este método obtiene un resumen del estado de las extensiones necesarias para el plugin
     *
     * @return array
     */
    public static function getExtensionsValidate()
    {
        $result = [];
        $extensions = [
            'curl',
            'json',
            'dom',
        ];
        foreach ($extensions as $value) {
            $result[$value] = InfoUtil::getCheckExtension($value);
        }

        return $result;
    }

    /**
     * Este método obtiene informacion relevante del servidor.
     *
     * @return array
     */
    public static function getServerResume()
    {
        return $_SERVER['SERVER_SOFTWARE'];
    }

    public static function getSummary(){
        return [
            'server'          => InfoUtil::getServerResume(),
            'phpExtensions'   => InfoUtil::getExtensionsValidate(),
            'php'             => InfoUtil::getValidatephp()
        ];
    }
}
