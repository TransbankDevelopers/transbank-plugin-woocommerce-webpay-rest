<?php

namespace Transbank\Plugin\Model;

class TbkTransaction
{
    private string $token;
    private string $url;
    private string $sessionId;
    private string $buyOrder;
    private int|float $amount;
    private string $environment;
    private string $commerceCode;
    private string $product;
    private string $status;
    private string $orderId;

    public function getToken(): string
    {
        return $this->token;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getBuyOrder(): string
    {
        return $this->buyOrder;
    }

    public function getAmount(): int|float
    {
        return $this->amount;
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function getCommerceCode(): string
    {
        return $this->commerceCode;
    }

    public function getProduct(): string
    {
        return $this->product;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function setBuyOrder(string $buyOrder): void
    {
        $this->buyOrder = $buyOrder;
    }

    public function setAmount(int|float $amount): void
    {
        $this->amount = $amount;
    }

    public function setEnvironment(string $environment): void
    {
        $this->environment = $environment;
    }

    public function setCommerceCode(string $commerceCode): void
    {
        $this->commerceCode = $commerceCode;
    }

    public function setProduct(string $product): void
    {
        $this->product = $product;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }
    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }
}
