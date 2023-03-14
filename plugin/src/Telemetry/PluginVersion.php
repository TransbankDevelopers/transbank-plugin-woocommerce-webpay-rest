<?php

namespace Transbank\WooCommerce\WebpayRest\Telemetry;
use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;
use GuzzleHttp\Client;

class PluginVersion
{
    protected $soapUri = 'http://www.cumbregroup.com/tbk-webservice/PluginVersion.php?wsdl';
    protected $client = null;
    
    const ENV_INTEGRATION = 'TEST';
    const ENV_PRODUCTION = 'LIVE';
    const PRODUCT_WEBPAY = 1;

    const ECOMMERCE_WOOCOMMERCE = 1;
    const ECOMMERCE_PRESTASHOP = 2;
    const ECOMMERCE_MAGENTO2 = 3;
    const ECOMMERCE_VIRTUEMART = 4;
    const ECOMMERCE_OPENCART = 5;
    const ECOMMERCE_SDK = 6;

    /**
     * PluginVersion constructor.
     */
    public function __construct()
    {
        if (!class_exists('SoapClient')) {
            return;
        }

        try {
            $this->client = new \SoapClient($this->soapUri);
        } catch (\Exception $exception) {
        }
    }

    public function registerVersion($commerceCode, $pluginVersion, $ecommerceVersion, $ecommerceId, $environment, $product)
    {
        $log = new LogHandler();
        $log->logInfo('AQUI SE GUARDO ALGO DE INFORMACION');

        $this->sendMetrics($commerceCode, $pluginVersion, $ecommerceVersion, $ecommerceId, $environment, $product); // Metricas TBK

        if ($this->client === null) {
            return null;
        }

        try {
            return $this->client->version_register($commerceCode, $pluginVersion, $ecommerceVersion, $ecommerceId, $environment, $product);
        } catch (\Exception $e) {
            // Si la conexión falla, simplemente no hacer nada.
        }

        return null;
    }

    public function sendMetrics($commerceCode, $pluginVersion, $ecommerceVersion, $ecommerceId, $environment, $product) {
        try {

            $log = new LogHandler();

            $log->logInfo(':: Metrics');
            $log->logInfo($commerceCode);

            $webpayPayload = [
                'ecommerceId' => $ecommerceId,    
                'plugin' => 'WooCommerce',
                'environment' => $environment,
                'product' => $product,
                'pluginVersion' => $pluginVersion,
                'commerceCode' => $commerceCode,
                'phpVersion' => phpversion(),
                'ecommerceVersion' => $ecommerceVersion
            ];

            $client = new Client();
            
            $client->request('POST', 'https://tbk-app-y8unz.ondigitalocean.app/records/newRecord', ['form_params' => $webpayPayload]);

            $log->logInfo(':: Saved');
        } catch (\Exception $e) {
            $log->logError($e->getMessage());
        }
    }
}
