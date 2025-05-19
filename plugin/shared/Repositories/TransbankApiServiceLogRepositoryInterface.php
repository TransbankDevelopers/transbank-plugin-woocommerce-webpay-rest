<?php

namespace Transbank\Plugin\Repositories;

interface TransbankApiServiceLogRepositoryInterface
{
    public function getTableName(): string;
    public function create($orderId, $service, $product, $enviroment, $commerceCode, $input, $response);
    public function createError($orderId, $service, $product, $enviroment, $commerceCode, $input, $error, $originalError, $customError);
}
