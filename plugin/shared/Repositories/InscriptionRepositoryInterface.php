<?php

namespace Transbank\Plugin\Repositories;

interface InscriptionRepositoryInterface
{
    public function getTableName(): string;
    public function create(array $data);
    public function update(string $transactionId, array $data);
    public function getByToken(string $token);
    public function checkExistTable(): array;
}
