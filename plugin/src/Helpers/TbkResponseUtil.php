<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use DateTime;
use DateTimeZone;
use Transbank\Plugin\Helpers\TbkConstants;

/**
 * Utility class for handling Transbank responses.
 */
class TbkResponseUtil
{
    /**
     * Get the payment type from its code.
     *
     * @param string $paymentType The code of the payment type.
     * @return string The corresponding payment type.
     */
    public static function getPaymentType(string $paymentType)
    {
        return TbkConstants::PAYMENT_TYPE[$paymentType] ?? $paymentType;
    }

    /**
     * Get the installment type from the payment type response.
     *
     * @param string $paymentType The code of the installment type.
     * @return string The corresponding installment type.
     */
    public static function getInstallmentType(string $paymentType)
    {
        return TbkConstants::PAYMENT_TYPE_CODE[$paymentType] ?? $paymentType;
    }

    /**
     * Get the transaction status description from response status.
     *
     * @param string $status The code of the transaction status.
     * @return string The description of the corresponding transaction status.
     */
    public static function getStatus(string $status)
    {
        return TbkConstants::STATUS_DESCRIPTION[$status] ?? $status;
    }

    /**
     * Get the formatted accounting date from response.
     *
     * @param string $accountingDate The accounting date in 'md' format.
     * @return string The accounting date in 'mm-dd' format.
     */
    public static function getAccountingDate(string $accountingDate)
    {
        $date = DateTime::createFromFormat('md', $accountingDate);

        if (!$date) {
            return $accountingDate;
        }

        return $date->format('m-d');
    }

    /**
     * Converts a string of transaction UTC date to local date with time difference.
     *
     * @param string $date an date/time string in UTC.
     * @return string the string of local date with time difference.
     */
    public static function transactionDateToLocalDate(string $date)
    {
        $utcDate = new DateTime($date, new DateTimeZone('UTC'));
        $utcDate->setTimeZone(new DateTimeZone(wc_timezone_string()));
        return $utcDate->format('d-m-Y H:i:s P');
    }

    public static function getAmountFormatted(int $amount): string
    {
        return '$' . number_format($amount, 0, ',', '.');
    }

    private static function getCommonFieldsFormatted(object $commitResponse): array
    {
        $utcDate = new DateTime($commitResponse->transactionDate, new DateTimeZone('UTC'));
        $utcDate->setTimeZone(new DateTimeZone(wc_timezone_string()));

        $buyOrder = $commitResponse->buyOrder;
        $cardNumber = "**** **** **** {$commitResponse->cardNumber}";
        $transactionDate = $utcDate->format('d-m-Y');
        $transactionTime = $utcDate->format('H:i:s');

        return [
            'buyOrder' => $buyOrder,
            'cardNumber' => $cardNumber,
            'transactionDate' => $transactionDate,
            'transactionTime' => $transactionTime
        ];
    }

}
