<?php

namespace Transbank\WooCommerce\WebpayRest\Services;

use Transbank\Webpay\Oneclick\MallInscription;
use Transbank\Webpay\Oneclick;
use Transbank\Webpay\Options;
use Transbank\Plugin\Model\TbkInscription;
use Transbank\Plugin\Helpers\TbkConstants;

class OneclickInscriptionService extends ProductBaseService
{
    /**
     * @var MallInscription
     */
    protected $mallInscription;

    public function __construct(
        $config
    ) {
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
     * @throws \Transbank\Webpay\Oneclick\Exceptions\InscriptionStartException
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return \Transbank\Webpay\Oneclick\Responses\InscriptionStartResponse
     */
    public function startInscription($userName, $email, $returnUrl)
    {
        return $this->mallInscription->start($userName, $email, $returnUrl);
    }

    /**
     * @param $token
     * @param $userName
     * @param $email
     *
     * @throws \Transbank\Webpay\Oneclick\Exceptions\InscriptionFinishException
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return \Transbank\Webpay\Oneclick\Responses\InscriptionFinishResponse
     */
    public function finishInscription($token)
    {
        return $this->mallInscription->finish($token);
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
        $data->username = $username;
        $data->email = $userEmail;
        $data->userId = $userId;
        $data->orderId = $orderId;
        $data->from = $from;
        $data->status = TbkConstants::TRANSACTION_STATUS_INITIALIZED;
        $data->environment = $this->getEnvironment();
        $data->commerceCode = $this->getCommerceCode();
        return $data;
    }

    public function deleteInscription(string $tbkUser, string $username)
    {
        $this->mallInscription->delete($tbkUser, $username);
    }
}
