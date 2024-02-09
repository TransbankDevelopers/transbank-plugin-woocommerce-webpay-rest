<?php

use Transbank\Plugin\Helpers\TbkConstants;

class TbkResponseUtil {
    public static function getPaymentType(string $paymentType) {
        return TbkConstants::PAYMENT_TYPE[$paymentType]?? $paymentType;
    }

    public static function getInstallmentType(string $paymentType) {
        return TbkConstants::INSTALLMENT_TYPE[$paymentType]?? $paymentType;
    }

    public static function getStatus(string $status) {
        return TbkConstants::STATUS_DESCRIPTION[$status]?? $status;
    }

    public static function getAccountingDate(string $accountingDate) {
        $date = DateTime::createFromFormat('md', $accountingDate);
        return $date->format('m-d');
    }
}
