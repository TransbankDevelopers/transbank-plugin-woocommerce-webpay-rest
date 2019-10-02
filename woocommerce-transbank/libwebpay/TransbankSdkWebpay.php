<?php

require_once(plugin_dir_path( __DIR__ ) . "vendor/autoload.php");
require_once('LogHandler.php');

use Transbank\Webpay\Configuration;
use Transbank\Webpay\Webpay;

class TransbankSdkWebpay {

    var $transaction;

    function __construct($config) {
        $this->log = new LogHandler();
        if (isset($config)) {
            $environment = isset($config["MODO"]) ? $config["MODO"] : 'INTEGRACION';
            $configuration = Configuration::forTestingWebpayPlusNormal();
            $configuration->setWebpayCert(Webpay::defaultCert($environment));

            if ($environment != 'INTEGRACION') {
                $configuration->setEnvironment($environment);
                $configuration->setCommerceCode($config["COMMERCE_CODE"]);
                $configuration->setPrivateKey($config["PRIVATE_KEY"]);
                $configuration->setPublicCert($config["PUBLIC_CERT"]);
            }

            $this->transaction = (new Webpay($configuration))->getNormalTransaction();
        }
    }

    public function getWebPayCertDefault() {
        return Webpay::defaultCert('INTEGRACION');
    }

	public function initTransaction($amount, $sessionId, $buyOrder, $returnUrl, $finalUrl) {
        $result = array();
		try{
            $txDate = date('d-m-Y');
            $txTime = date('H:i:s');
            $this->log->logInfo('initTransaction - amount: ' . $amount . ', sessionId: ' . $sessionId .
                                ', buyOrder: ' . $buyOrder . ', txDate: ' . $txDate . ', txTime: ' . $txTime);
            $initResult = $this->transaction->initTransaction($amount, $buyOrder, $sessionId, $returnUrl, $finalUrl);
            $this->log->logInfo('initTransaction - initResult: ' . json_encode($initResult));
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

    public function commitTransaction($tokenWs) {
        $result = array();
        try{
            $this->log->logInfo('getTransactionResult - tokenWs: ' . $tokenWs);
            if ($tokenWs == null) {
                throw new Exception("El token webpay es requerido");
            }
            return $this->transaction->getTransactionResult($tokenWs);
        } catch(Exception $e) {
            $result = array(
                "error" => 'Error al confirmar la transacción',
                "detail" => $e->getMessage()
            );
            $this->log->logError(json_encode($result));
        }
        return $result;
    }
}
