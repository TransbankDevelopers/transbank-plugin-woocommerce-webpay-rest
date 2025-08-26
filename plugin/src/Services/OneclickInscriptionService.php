<?php

namespace Transbank\WooCommerce\WebpayRest\Services;

use \Exception;
use Transbank\Webpay\Oneclick\MallInscription;
use Transbank\Webpay\Oneclick;
use Transbank\Webpay\Options;
use Transbank\Plugin\Exceptions\Oneclick\FinishOneclickException;
use Transbank\Plugin\Exceptions\Oneclick\StartOneclickException;
use Transbank\Plugin\Model\TbkInscription;
use Transbank\Plugin\Helpers\TbkConstants;

class OneclickInscriptionService extends ProductBaseService
{
    /**
     * @var MallInscription
     */
    protected $mallInscription;

    public function __construct(
        $log,
        $config,
    ) {
        $this->log = $log;
        if ($config->getEnvironment() == Options::ENVIRONMENT_PRODUCTION) {
            $this->mallInscription = MallInscription::buildForProduction(
                $config->getApikey(),
                $config->getCommerceCode()
            );
        } else {
            $this->mallInscription = MallInscription::buildForIntegration(
                Oneclick::INTEGRATION_API_KEY,
                Oneclick::INTEGRATION_COMMERCE_CODE
            );
        }
        $this->options = $this->mallInscription->getOptions();
    }

    /**
     * @param $userName
     * @param $email
     * @param $returnUrl
     *
     * @throws StartOneclickException
     *
     * @return \Transbank\Webpay\Oneclick\Responses\InscriptionStartResponse
     */
    public function startInscription($userName, $email, $returnUrl)
    {
        try {
            $txDate = date('d-m-Y');
            $txTime = date('H:i:s');
            $this->log->logInfo('startInscription - userName: ' . $userName . ', email: ' . $email .
                ', txDate: ' . $txDate . ', txTime: ' . $txTime);

            $resp = $this->mallInscription->start($userName, $email, $returnUrl);
            $this->log->logInfo('startInscription - resp: ' . json_encode($resp));
            if (isset($resp) && isset($resp->urlWebpay) && isset($resp->token)) {
                return $resp;
            } else {
                $errorMessage = "Error al iniciar la inscripción para => userName: {$userName}, email: {$email}";
                throw new StartOneclickException($errorMessage);
            }
        } catch (Exception $e) {
            $errorMessage = "Error al iniciar la inscripción para =>
                userName: {$userName}, email: {$email}, error: {$e->getMessage()}";
            $this->log->logError($errorMessage);
            throw new StartOneclickException($errorMessage, $e);
        }
    }

    /**
     * @param $token
     * @param $userName
     * @param $email
     *
     * @throws FinishOneclickException
     *
     * @return \Transbank\Webpay\Oneclick\Responses\InscriptionFinishResponse
     */
    public function finishInscription($token, $userName, $email)
    {
        try {
            $txDate = date('d-m-Y');
            $txTime = date('H:i:s');
            $this->log->logInfo('finish => token: ' . $token . ' userName: ' . $userName . ', email: ' . $email .
                ', txDate: ' . $txDate . ', txTime: ' . $txTime);
            $resp = $this->mallInscription->finish($token);
            $this->log->logInfo('finish - resp: ' . json_encode($resp));
            return $resp;
        } catch (Exception $e) {
            $errorMessage = "Error al confirmar la inscripción para =>
                userName: {$userName}, email: {$email}, error: {$e->getMessage()}";
            $this->log->logError($errorMessage);
            throw new FinishOneclickException($errorMessage, $e);
        }
    }

    private function generateUsername($userId)
    {
        return 'wc:' . $this->generateRandomId() . ':' . $userId;
    }

    private function generateRandomId($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    public function prepareInscription(
        $userId,
        $userEmail,
        $orderId,
        $from = 'checkout',

    ): TbkInscription {
        $username = $this->generateUsername($userId);
        $data = new TbkInscription();
        $data->setUsername($username);
        $data->setEmail($userEmail);
        $data->setUserId($userId);
        $data->setOrderId($orderId);
        $data->setFrom($from);
        $data->setStatus(TbkConstants::TRANSACTION_STATUS_INITIALIZED);
        $data->setEnvironment($this->getEnviroment());
        $data->setCommerceCode($this->getCommerceCode());
        return $data;
    }
}
