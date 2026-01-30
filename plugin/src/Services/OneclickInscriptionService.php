<?php

namespace Transbank\WooCommerce\WebpayRest\Services;

use Transbank\Webpay\Oneclick\MallInscription;
use Transbank\Webpay\Oneclick;
use Transbank\Webpay\Options;
use Transbank\Plugin\Model\TbkInscription;
use Transbank\Plugin\Helpers\TbkConstants;
use Transbank\WooCommerce\WebpayRest\Repositories\InscriptionRepository;
use Transbank\WooCommerce\WebpayRest\Repositories\PaymentTokenRepository;

class OneclickInscriptionService extends ProductBaseService
{
    /**
     * @var MallInscription
     */
    protected $mallInscription;

    private InscriptionRepository $inscriptionRepository;
    private PaymentTokenRepository $paymentTokenRepository;

    public function __construct(
        $config,
        InscriptionRepository $inscriptionRepository,
        PaymentTokenRepository $paymentTokenRepository
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
        $this->inscriptionRepository = $inscriptionRepository;
        $this->paymentTokenRepository = $paymentTokenRepository;
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

    public function deleteByPaymentTokenId(int $paymentTokenId): TbkInscription
    {
        $inscription = $this->inscriptionRepository->findByPaymentTokenId($paymentTokenId);

        if (!$inscription) {
            throw new \Exception('No se encontró inscripción asociada al token de pago.');
        }

        $this->deleteInscription($inscription->tbkUser, $inscription->username);
        $this->inscriptionRepository->deleteById($inscription->id);

        return $inscription;
    }

    public function deleteByInscriptionId(int $inscriptionId): void
    {
        $record = $this->inscriptionRepository->findById($inscriptionId);

        if (!$record) {
            throw new \Exception('Inscripción no encontrada.');
        }

        $inscription = new TbkInscription($record);
        $paymentTokenId = $inscription->tokenId;

        if ($paymentTokenId <= 0) {
            $paymentTokenId = $this->paymentTokenRepository->findTokenIdByUserAndUsername(
                $inscription->userId,
                $inscription->username
            );
        }

        if (!$paymentTokenId) {
            throw new \Exception('Payment token no encontrado para eliminar.');
        }

        $this->deleteInscription($inscription->tbkUser, $inscription->username);
        $this->deleteLocalInscriptionAndToken($paymentTokenId, $inscription->id);
    }

    private function deleteLocalInscriptionAndToken(int $paymentTokenId, int $inscriptionId): void
    {
        global $wpdb;

        $wpdb->query('START TRANSACTION');

        try {
            $this->paymentTokenRepository->deleteById($paymentTokenId);
            $this->inscriptionRepository->deleteById($inscriptionId);
            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }
}
