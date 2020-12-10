<?php
namespace Transbank\WooCommerce\WebpayRest;
use Transbank\WooCommerce\WebpayRest\Helpers\ConfigProvider;
use Exception;
use Transbank\Webpay\Options;
use Transbank\Webpay\WebpayPlus;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionCreateException;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionCommitException;
use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;

/**
 * Class TransbankSdkWebpayRest
 * @package Transbank\WooCommerce\WebpayRest
 */
class TransbankSdkWebpayRest {

    /**
     * @var Options
     */
    var $options;
    /**
     * @var LogHandler
     */
    protected $log;

    /**
     * TransbankSdkWebpayRest constructor.
     * @param $config
     */
    function __construct($config = null) {
        $this->log = new LogHandler();
        if (!isset($config)) {

            $configProvider = new ConfigProvider();
            $config = [
                'MODO' => $configProvider->getConfig('webpay_rest_environment'),
                'COMMERCE_CODE' => $configProvider->getConfig('webpay_rest_commerce_code'),
                'API_KEY' => $configProvider->getConfig('webpay_rest_api_key'),
            ];

        }
        $environment = isset($config["MODO"]) ? $config["MODO"] : 'TEST';
        $this->options = ($environment != 'TEST') ? new Options($config["API_KEY"], $config["COMMERCE_CODE"]) : Options::defaultConfig();
        $this->options->setIntegrationType($environment);
}

    /**
     * @param $amount
     * @param $sessionId
     * @param $buyOrder
     * @param $returnUrl
     * @return array
     * @throws Exception
     */
    public function createTransaction($amount, $sessionId, $buyOrder, $returnUrl) {
        $result = array();
        try{

            $txDate = date('d-m-Y');
            $txTime = date('H:i:s');
            $this->log->logInfo('initTransaction - amount: ' . $amount . ', sessionId: ' . $sessionId .
                ', buyOrder: ' . $buyOrder . ', txDate: ' . $txDate . ', txTime: ' . $txTime);

            $initResult = WebpayPlus\Transaction::create($buyOrder, $sessionId, $amount, $returnUrl, $this->options);


            $this->log->logInfo('createTransaction - initResult: ' . json_encode($initResult));
            if (isset($initResult) && isset($initResult->url) && isset($initResult->token)) {
                $result = array(
                    "url" => $initResult->url,
                    "token_ws" => $initResult->token
                );
            } else {
                throw new Exception('No se ha creado la transacción para, amount: ' . $amount . ', sessionId: ' . $sessionId . ', buyOrder: ' . $buyOrder);
            }
        } catch(Exception $e) {

            $result = array(
                "error" => 'Error al crear la transacción',
                "detail" => $e->getMessage()
            );
            $this->log->logError(json_encode($result));
        }
        return $result;
    }

    /**
     * @param $tokenWs
     * @return array|WebpayPlus\TransactionCommitResponse
     * @throws Exception
     */
    public function commitTransaction($tokenWs) {
        try{
            $this->log->logInfo('getTransactionResult - tokenWs: ' . $tokenWs);
            if ($tokenWs == null) {
                throw new Exception("El token webpay es requerido");
            }
            return WebpayPlus\Transaction::commit($tokenWs,$this->options);
        } catch(TransactionCommitException $e) {
            $result = array(
                "error" => 'Error al confirmar la transacción',
                "detail" => $e->getMessage()
            );
            $this->log->logError(json_encode($result));
        }
        return $result;
    }

    public function refund($token, $amount)
    {
        return WebpayPlus\Transaction::refund($token, $amount, $this->options);
    }

    public function status($token)
    {
        return WebpayPlus\Transaction::getStatus($token, $this->options);
    }
}
