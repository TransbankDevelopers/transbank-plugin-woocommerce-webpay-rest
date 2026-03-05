<?php

namespace Transbank\Plugin\Model;

class TbkTransaction
{
    public int $id;
    private string $token;
    private string $url;
    private string $sessionId;
    private string $buyOrder;
    private string $childBuyOrder;
    private int|float $amount;
    private string $environment;
    private string $commerceCode;
    private string $childCommerceCode;
    private string $product;
    private string $status;
    private string $orderId;

    private string $transbankStatus;
    private string $transbankResponse;
    private string $lastRefundType;
    private string $lastRefundResponse;
    private string $error;
    private string $detailError;
    private string $createdAt;

    public function __construct(?object $record = null)
    {
        if ($record !== null) {
            $this->id = (int) ($record->id ?? 0);
            $this->token = $record->token ?? '';
            $this->url = $record->url ?? '';
            $this->sessionId = $record->session_id ?? '';
            $this->buyOrder = $record->buy_order ?? '';
            $this->childBuyOrder = $record->child_buy_order ?? '';
            $this->amount = is_numeric($record->amount ?? null) ? $record->amount : 0;
            $this->environment = $record->environment ?? '';
            $this->commerceCode = $record->commerce_code ?? '';
            $this->childCommerceCode = $record->child_commerce_code ?? '';
            $this->product = $record->product ?? '';
            $this->status = $record->status ?? '';
            $this->orderId = $record->order_id ?? '';
            $this->transbankStatus = $record->transbank_status ?? '';
            $this->transbankResponse = $record->transbank_response ?? '';
            $this->lastRefundType = $record->last_refund_type ?? '';
            $this->lastRefundResponse = $record->last_refund_response ?? '';
            $this->error = $record->error ?? '';
            $this->detailError = $record->detail_error ?? '';
            $this->createdAt = $record->created_at ?? '';
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function getBuyOrder(): string
    {
        return $this->buyOrder;
    }

    public function setBuyOrder(string $buyOrder): void
    {
        $this->buyOrder = $buyOrder;
    }

    public function getChildBuyOrder(): string
    {
        return $this->childBuyOrder;
    }

    public function setChildBuyOrder(string $childBuyOrder): void
    {
        $this->childBuyOrder = $childBuyOrder;
    }

    public function getAmount(): int|float
    {
        return $this->amount;
    }

    public function setAmount(int|float $amount): void
    {
        $this->amount = $amount;
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function setEnvironment(string $environment): void
    {
        $this->environment = $environment;
    }

    public function getCommerceCode(): string
    {
        return $this->commerceCode;
    }

    public function setCommerceCode(string $commerceCode): void
    {
        $this->commerceCode = $commerceCode;
    }

    public function getChildCommerceCode(): string
    {
        return $this->childCommerceCode;
    }

    public function setChildCommerceCode(string $childCommerceCode): void
    {
        $this->childCommerceCode = $childCommerceCode;
    }

    public function getProduct(): string
    {
        return $this->product;
    }

    public function setProduct(string $product): void
    {
        $this->product = $product;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }
}
