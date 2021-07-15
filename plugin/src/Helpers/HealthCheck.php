<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\WooCommerce\WebpayRest\TransbankSdkWebpayRest;

if (!defined('ABSPATH')) {
    exit;
}

class HealthCheck
{
    public $apiKey;
    public $commerceCode;
    public $environment;
    public $extensions;
    public $versionInfo;
    public $resume;
    public $fullResume;
    public $ecommerce;
    public $config;

    public function __construct($config)
    {
        $this->config = $config;
        $this->environment = $config['MODO'];
        $this->commerceCode = $config['COMMERCE_CODE'];
        $this->apiKey = $config['API_KEY'];
        $this->ecommerce = $config['ECOMMERCE'];
        // extensiones necesarias
        $this->extensions = [
            'dom',
            'curl',
            'json',
        ];
    }

    // valida version de php
    private function getValidatephp()
    {
        if (version_compare(phpversion(), '7.0.0', '>=')) {
            $this->versionInfo = [
                'status'  => 'OK',
                'version' => phpversion(),
            ];
        } else {
            $this->versionInfo = [
                'status'  => 'Error!: Version no soportada',
                'version' => phpversion(),
            ];
        }

        return $this->versionInfo;
    }

    // verifica si existe la extension y cual es la version de esta
    private function getCheckExtension($extension)
    {
        if (extension_loaded($extension)) {
            if ($extension == 'openssl') {
                $version = OPENSSL_VERSION_TEXT;
            } else {
                $version = phpversion($extension);
                if (empty($version) or $version == null or $version === false or $version == ' ' or $version == '') {
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

    //obtiene ultimas versiones
    // obtiene versiones ultima publica en github (no compatible con virtuemart) lo ideal es que el :usuario/:repo sean entregados como string
    // permite un maximo de 60 consultas por hora
    private function getLastGitHubReleaseVersion($string)
    {
        $baseurl = 'https://api.github.com/repos/'.$string.'/releases/latest';
        $response = wp_remote_get($baseurl);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        $version = is_array($json) && array_key_exists('tag_name', $json) ? $json['tag_name'] : '-';

        return $version;
    }

    // funcion para obtener info de cada ecommerce, si el ecommerce es incorrecto o no esta seteado se escapa como respuesta "NO APLICA"
    private function getEcommerceInfo($ecommerce)
    {
        if (!class_exists('WooCommerce')) {
            exit;
        } else {
            global $woocommerce;
            if (!$woocommerce->version) {
                exit;
            } else {
                $actualversion = $woocommerce->version;
                $lastversion = $this->getLastGitHubReleaseVersion('woocommerce/woocommerce');
                $file = __DIR__.'/../../webpay-rest.php';
                $search = ' * Version:';
                $lines = file($file);
                foreach ($lines as $line) {
                    if (strpos($line, $search) !== false) {
                        $currentplugin = str_replace(' * Version:', '', $line);
                    }
                }
            }
        }
        $result = [
            'current_ecommerce_version' => $actualversion,
            'last_ecommerce_version'    => $lastversion,
            'current_plugin_version'    => $currentplugin,
        ];

        return $result;
    }

    // creacion de retornos
    // arma array que entrega informacion del ecommerce: nombre, version instalada, ultima version disponible
    private function getPluginInfo($ecommerce)
    {
        $data = $this->getEcommerceInfo($ecommerce);
        $result = [
            'ecommerce'              => $ecommerce,
            'ecommerce_version'      => $data['current_ecommerce_version'],
            'current_plugin_version' => $data['current_plugin_version'],
            'last_plugin_version'    => $this->getPluginLastVersion(),
        ];

        return $result;
    }

    // arma array con informacion del último plugin compatible con el ecommerce
    private function getPluginLastVersion()
    {
        $response = wp_remote_get('https://api.github.com/repos/TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest/releases/latest');
        $body = wp_remote_retrieve_body($response);

        $json = json_decode($body, true);
        if (isset($json['message'])) {
            return 'No se pudo obtener';
        }

        $tag_name = $json['tag_name'];

        return $tag_name;
    }

    // lista y valida extensiones/ modulos de php en servidor ademas mostrar version
    private function getExtensionsValidate()
    {
        foreach ($this->extensions as $value) {
            $this->resExtensions[$value] = $this->getCheckExtension($value);
        }

        return $this->resExtensions;
    }

    // crea resumen de informacion del servidor. NO incluye a PHP info
    private function getServerResume()
    {
        // arma array de despliegue
        $this->resume = [
            'php_version'    => $this->getValidatephp(),
            'server_version' => ['server_software' => $_SERVER['SERVER_SOFTWARE']],
            'plugin_info'    => $this->getPluginInfo($this->ecommerce),
        ];

        return $this->resume;
    }

    // crea array con la informacion de comercio para posteriormente exportarla via json
    private function getCommerceInfo()
    {
        $result = [
            'environment'   => $this->environment,
            'commerce_code' => $this->commerceCode,
            'api_key'       => $this->apiKey,
        ];

        return ['data' => $result];
    }

    // guarda en array informacion de funcion phpinfo
    private function getPhpInfo()
    {
        ob_start();
        phpinfo();
        $info = ob_get_contents();
        ob_end_clean();
        $newinfo = strstr($info, '<table>');
        $newinfo = strstr($newinfo, '<h1>PHP Credits</h1>', true);
        $return = ['string' => ['content' => str_replace('</div></body></html>', '', $newinfo)]];

        return $return;
    }

    public function setCreateTransaction()
    {
        $transbankSdkWebpay = new TransbankSdkWebpayRest();

        $amount = 990;
        $buyOrder = '_Healthcheck_';
        $sessionId = uniqid();
        $returnUrl = 'http://test.com/test';

        $result = $transbankSdkWebpay->createTransaction($amount, $sessionId, $buyOrder, $returnUrl);
        $status = 'Error';
        if ($result) {
            if (!empty($result['error']) && isset($result['error'])) {
                $status = 'Error';
            } else {
                $status = 'OK';
            }
        } else {
            if (array_key_exists('error', $result)) {
                $status = 'Error';
            }
        }

        return [
            'status'   => ['string' => $status],
            'response' => $result,
        ];
    }

    //compila en solo un metodo toda la informacion obtenida, lista para imprimir
    private function getFullResume()
    {
        $this->fullResume = [
            'server_resume'         => $this->getServerResume(),
            'php_extensions_status' => $this->getExtensionsValidate(),
            'commerce_info'         => $this->getCommerceInfo(),
            'php_info'              => $this->getPhpInfo(),
        ];

        return $this->fullResume;
    }

    // imprime informacion de comercio y llaves
    public function printCommerceInfo()
    {
        return json_encode($this->getCommerceInfo());
    }

    public function printPhpInfo()
    {
        return json_encode($this->getPhpInfo());
    }

    // imprime en formato json la validacion de extensiones / modulos de php
    public function printExtensionStatus()
    {
        return json_encode($this->getExtensionsValidate());
    }

    // imprime en formato json informacion del servidor
    public function printServerResume()
    {
        return json_encode($this->getServerResume());
    }

    // imprime en formato json el resumen completo
    public function printFullResume()
    {
        return json_encode($this->getFullResume());
    }

    public function getInitTransaction()
    {
        return json_encode($this->setCreateTransaction());
    }
}
