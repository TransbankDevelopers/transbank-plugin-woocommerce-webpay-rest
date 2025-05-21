<?php

namespace Transbank\Plugin\Model;
use Transbank\Webpay\Options;

abstract class ProductConfig {
    public $apikey = null;
    public $commerceCode = null;
    public $environment = null;
    public $buyOrderFormat = null;
    public $statusAfterPayment = null;

    public function __construct($data = null) {
        if (!is_null($data)) {
            $this->apikey = $data['apikey'] ?? null;
            $this->commerceCode = $data['commerceCode'] ?? null;
            $this->environment = $data['environment'] ?? null;
            $this->buyOrderFormat = $data['buyOrderFormat'] ?? null;
            $this->statusAfterPayment = $data['statusAfterPayment'] ?? null;
        }
    }

    public function getApikey()
    {
        return $this->apikey;
    }

    public function getCommerceCode()
    {
        return $this->commerceCode;
    }

    public function getEnvironment()
    {
        return $this->environment;
    }

    public function getBuyOrderFormat()
    {
        return $this->buyOrderFormat;
    }

    public function getStatusAfterPayment()
    {
        return $this->statusAfterPayment;
    }

    public function isIntegration()
    {
        return $this->getEnvironment() == Options::ENVIRONMENT_INTEGRATION;
    }
}
