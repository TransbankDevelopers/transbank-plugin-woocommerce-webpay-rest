<?php

namespace Transbank\Plugin\Repositories;

interface TransbankExecutionErrorLogRepositoryInterface
{
    public function getTableName(): string;
    public function create($orderId, $service, $product, $enviroment, $commerceCode, $data, $error, $originalError, $customError);
}
