<?php

namespace Transbank\WooCommerce\WebpayRest\Services;

use Transbank\Webpay\Oneclick;
use Transbank\Webpay\Options;
use Transbank\Plugin\Model\TbkTransaction;
use Transbank\Plugin\Helpers\TbkConstants;
use Transbank\Plugin\Helpers\BuyOrderHelper;
use Transbank\Webpay\Oneclick\MallTransaction;

class OneclickAuthorizationService extends ProductBaseService
{
    const BUY_ORDER_FORMAT = 'wc-{random, length=8}-{orderId}';
    const CHILD_BUY_ORDER_FORMAT = 'wc-child-{random, length=8}-{orderId}';

    /**
     * @var MallTransaction
     */
    protected $mallTransaction;
    private $childBuyOrderFormat;
    private $childCommerceCode;

    public function __construct(
        $config
    ) {
        if ($config->getEnvironment() == Options::ENVIRONMENT_PRODUCTION) {
            $this->mallTransaction = MallTransaction::buildForProduction(
                $config->getApikey(),
                $config->getCommerceCode()
            );
            $this->childCommerceCode = $config->getChildCommerceCode();
        } else {
            $this->mallTransaction = MallTransaction::buildForIntegration(
                Oneclick::INTEGRATION_API_KEY,
                Oneclick::INTEGRATION_COMMERCE_CODE
            );
            $this->childCommerceCode = Oneclick::INTEGRATION_CHILD_COMMERCE_CODE_1;
        }
        $this->options = $this->mallTransaction->getOptions();
        $this->buyOrderFormat = BuyOrderHelper::isValidFormat(
            $config->getBuyOrderFormat()
        ) ? $config->getBuyOrderFormat() : self::BUY_ORDER_FORMAT;
        $this->childBuyOrderFormat = BuyOrderHelper::isValidFormat(
            $config->getChildBuyOrderFormat()
        ) ? $config->getChildBuyOrderFormat() : self::CHILD_BUY_ORDER_FORMAT;
    }

    /**
     * @param $username
     * @param $tbkUser
     * @param $parentBuyOrder
     * @param $childBuyOrder
     * @param $amount
     *
     * @throws \Transbank\Webpay\Oneclick\Exceptions\MallTransactionAuthorizeException
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return \Transbank\Webpay\Oneclick\Responses\MallTransactionAuthorizeResponse
     */
    public function authorize($username, $tbkUser, $parentBuyOrder, $childBuyOrder, $amount)
    {
        $details = [
            [
                'commerce_code' => $this->getChildCommerceCode(),
                'buy_order' => $childBuyOrder,
                'amount' => $amount,
                'installments_number' => 1,
            ],
        ];
        return $this->mallTransaction->authorize($username, $tbkUser, $parentBuyOrder, $details);
    }

    /**
     * @param $buyOrder
     *
     * @throws \Transbank\Webpay\Oneclick\Exceptions\MallTransactionStatusException
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return \Transbank\Webpay\Oneclick\Responses\MallTransactionStatusResponse
     */
    public function status($buyOrder)
    {
        return $this->mallTransaction->status($buyOrder);
    }

    /**
     * @param $token
     * @param $amount
     *
     * @throws \Transbank\Webpay\Oneclick\Exceptions\MallRefundTransactionException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * 
     * @return \Transbank\Webpay\Oneclick\Responses\MallTransactionRefundResponse
     */
    public function refund($buyOrder, $childCommerceCode, $childBuyOrder, $amount)
    {
            return $this->mallTransaction->refund($buyOrder, $childCommerceCode, $childBuyOrder, $amount);
    }

    public function prepareTransaction($orderId, $amount): TbkTransaction
    {
        $parentBuyOrder = $this->generateBuyOrder($orderId);
        $childBuyOrder = $this->generateChildBuyOrder($orderId);
        $data = new TbkTransaction();
        $data->setBuyOrder($parentBuyOrder);
        $data->setChildBuyOrder($childBuyOrder);
        $data->setAmount($amount);
        $data->setOrderId($orderId);
        $data->setEnvironment($this->getEnvironment());
        $data->setCommerceCode($this->getCommerceCode());
        $data->setChildCommerceCode($this->getChildCommerceCode());
        $data->setProduct(TbkConstants::TRANSACTION_WEBPAY_ONECLICK);
        $data->setStatus(TbkConstants::TRANSACTION_STATUS_INITIALIZED);
        return $data;
    }

    public function getChildCommerceCode()
    {
        return $this->childCommerceCode;
    }

    private function generateChildBuyOrder($orderId)
    {
        return BuyOrderHelper::generateFromFormat($this->childBuyOrderFormat, $orderId);
    }
}
