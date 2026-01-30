<?php

namespace Transbank\Plugin\Model;

class TbkInscription
{
    public int $id;
    public string $token;
    public string $username;
    public ?string $tbkUser;
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
            $this->tbkUser = $this->extractTbkUserFromResponse($record->transbank_response ?? '');
        }
    }

    private function extractTbkUserFromResponse(string $response): ?string
    {
        $data = json_decode($response, true);
        return $data['tbkUser'] ?? null;
    }
}
