<?php

namespace Transbank\WooCommerce\WebpayRest;

use Exception;
use Transbank\Webpay\Options;
use Transbank\Webpay\WebpayPlus;
use Transbank\Webpay\WebpayPlus\Exceptions\TransactionCommitException;
use Transbank\Webpay\WebpayPlus\Transaction;
use Transbank\WooCommerce\WebpayRest\Helpers\ConfigProvider;
use Transbank\WooCommerce\WebpayRest\Helpers\LogHandler;
use Transbank\WooCommerce\WebpayRest\Helpers\InteractsWithFullLog;

/**
 * Class TransbankSdkWebpayRest.
 */
class TransbankSdkWebpayRest
{
    /**
     * @var Options
     */
    public $options;
    /**
     * @var LogHandler
     */
    protected $log;
    /**
     * @var InteractsWithFullLog
     */
    protected $interactsWithFullLog;

    protected $transaction = null;

    /**
     * TransbankSdkWebpayRest constructor.
     *
     * @param $config
     */
    public function __construct($config = null)
    {
        $this->log = new LogHandler();
        if (!isset($config)) {
            $configProvider = new ConfigProvider();
            $config = [
                'MODO'          => $configProvider->getConfig('webpay_rest_environment'),
                'COMMERCE_CODE' => $configProvider->getConfig('webpay_rest_commerce_code'),
                'API_KEY'       => $configProvider->getConfig('webpay_rest_api_key'),
            ];
        }
        $environment = isset($config['MODO']) ? $config['MODO'] : 'TEST';

        $options = Transaction::getDefaultOptions();
        if ($environment !== 'TEST') {
            $options = Options::forProduction($config['COMMERCE_CODE'], $config['API_KEY']);
        }

        $this->transaction = new Transaction($options);
        $this->interactsWithFullLog = new InteractsWithFullLog();
    }

    /**
     * @param $amount
     * @param $sessionId
     * @param $buyOrder
     * @param $returnUrl
     *
     * @throws Exception
     *
     * @return array
     */
    public function createTransaction($amount, $sessionId, $buyOrder, $returnUrl)
    {
        $result = [];

        try {
            $this->interactsWithFullLog->logWebpayPlusIniciando();

            $txDate = date('d-m-Y');
            $txTime = date('H:i:s');
            $this->log->logInfo('initTransaction - amount: '.$amount.', sessionId: '.$sessionId.
                ', buyOrder: '.$buyOrder.', txDate: '.$txDate.', txTime: '.$txTime);

            $this->interactsWithFullLog->logWebpayPlusAntesCrearTx($amount, $sessionId, $buyOrder, $returnUrl); // Logs
            $initResult = $this->transaction->create($buyOrder, $sessionId, $amount, $returnUrl);

            $this->log->logInfo('createTransaction - initResult: '.json_encode($initResult));
            $this->interactsWithFullLog->logWebpayPlusDespuesCrearTx($initResult); // Logs
            if (isset($initResult) && isset($initResult->url) && isset($initResult->token)) {
                $result = [
                    'url'      => $initResult->url,
                    'token_ws' => $initResult->token,
                ];
            } else {
                $this->interactsWithFullLog->logWebpayPlusDespuesCrearTxError($initResult); // Logs
                throw new Exception('No se ha creado la transacción para, amount: '.$amount.', sessionId: '.$sessionId.', buyOrder: '.$buyOrder);
            }
        } catch (Exception $e) {
            $result = [
                'error'  => 'Error al crear la transacción',
                'detail' => $e->getMessage(),
            ];
            $this->log->logError(json_encode($result));
        }

        return $result;
    }

    /**
     * @param $tokenWs
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws Exception
     *
     * @return array|WebpayPlus\TransactionCommitResponse
     */
    public function commitTransaction($tokenWs)
    {
        try {
            $this->log->logInfo('getTransactionResult - tokenWs: '.$tokenWs);
            if ($tokenWs == null) {
                throw new Exception('El token webpay es requerido');
            }

            return $this->transaction->commit($tokenWs);
        } catch (TransactionCommitException $e) {
            $result = [
                'error'  => 'Error al confirmar la transacción',
                'detail' => $e->getMessage(),
            ];
            $this->log->logError(json_encode($result));
        }

        return $result;
    }

    public function refund($token, $amount)
    {
        return $this->transaction->refund($token, $amount);
    }

    public function status($token)
    {
        return $this->transaction->status($token);
    }

    /**
     * @return Transaction|null
     */
    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }
}
