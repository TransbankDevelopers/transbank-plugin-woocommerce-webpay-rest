<?php

namespace Transbank\Plugin\Model;

class TbkInscription
{
    public int $id;
    public string $token;
    public string $username;
    public string $email;
    public int $userId;
    public int $tokenId;
    public int $orderId;
    public bool $payAfterInscription;
    public bool $finished;
    public string $responseCode;
    public string $authorizationCode;
    public string $cardType;
    public string $cardNumber;
    public string $from;
    public string $status;
    public string $environment;
    public string $commerceCode;
    public string $transbankResponse;
    public string $error;
    public string $detailError;
    public string $createdAt;

    public function __construct(?object $record = null)
    {
        if ($record !== null) {
            $this->id = (int) ($record->id ?? 0);
            $this->token = $record->token ?? '';
            $this->username = $record->username ?? '';
            $this->email = $record->email ?? '';
            $this->userId = (int) ($record->user_id ?? 0);
            $this->tokenId = (int) ($record->token_id ?? 0);
            $this->orderId = (int) ($record->order_id ?? 0);
            $this->payAfterInscription = (bool) ($record->pay_after_inscription ?? false);
            $this->finished = (bool) ($record->finished ?? false);
            $this->responseCode = $record->response_code ?? '';
            $this->authorizationCode = $record->authorization_code ?? '';
            $this->cardType = $record->card_type ?? '';
            $this->cardNumber = $record->card_number ?? '';
            $this->from = $record->from ?? '';
            $this->status = $record->status ?? '';
            $this->environment = $record->environment ?? '';
            $this->commerceCode = $record->commerce_code ?? '';
            $this->transbankResponse = $record->transbank_response ?? '';
            $this->error = $record->error ?? '';
            $this->detailError = $record->detail_error ?? '';
            $this->createdAt = $record->created_at ?? '';
        }
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function setOrderId(int $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function setFrom(string $from): void
    {
        $this->from = $from;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
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
}
