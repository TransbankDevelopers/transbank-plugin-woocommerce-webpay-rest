<?php

namespace Transbank\Plugin\Helpers;

class TbkConstants
{
    const FLAG_ACTIVE = '1';
    const FLAG_INACTIVE = '2';

    const TRANSACTION_TABLE_NAME = 'transbank_transaction';
    const INSCRIPTIONS_TABLE_NAME = 'transbank_inscription';
    const API_SERVICE_LOG_TABLE_NAME = 'transbank_api_service_log';
    const EXECUTION_ERROR_LOG_TABLE_NAME = 'transbank_execution_error_log';

    const TRANSACTION_WEBPAY_PLUS = 'webpay_plus';
    const TRANSACTION_WEBPAY_PLUS_MALL = 'webpay_plus_mall';
    const TRANSACTION_WEBPAY_ONECLICK = 'webpay_oneclick';

    const TRANSACTION_TBK_STATUS_AUTHORIZED = 'AUTHORIZED';
    const TRANSACTION_TBK_STATUS_NULLIFIED = 'NULLIFIED';
    const TRANSACTION_TBK_STATUS_REVERSED = 'REVERSED';
    const TRANSACTION_TBK_STATUS_PARTIALLY_NULLIFIED = 'PARTIALLY_NULLIFIED';
    const TRANSACTION_TBK_STATUS_CAPTURED = 'PARTIALLY_NULLIFIED';
    const TRANSACTION_TBK_STATUS_FAILED = 'FAILED';
    const TRANSACTION_TBK_REFUND_NULLIFIED = 'NULLIFIED';
    const TRANSACTION_TBK_REFUND_REVERSED = 'REVERSED';
    const TRANSACTION_STATUS_PREPARED = 'prepared';
    const TRANSACTION_STATUS_INITIALIZED = 'initialized';
    const TRANSACTION_STATUS_FAILED = 'failed';
    const TRANSACTION_STATUS_ABORTED_BY_USER = 'aborted_by_user';
    const TRANSACTION_STATUS_APPROVED = 'approved';
    const TRANSACTION_STATUS_ECOMMERCE_APPROVED = 'ecommerce_approved';

    const INSCRIPTIONS_STATUS_INITIALIZED = 'initialized';
    const INSCRIPTIONS_STATUS_FAILED = 'failed';
    const INSCRIPTIONS_STATUS_COMPLETED = 'completed';

    const WEBPAYPLUS_CREATE = 'create';
    const WEBPAYPLUS_COMMIT = 'commit';
    const WEBPAYPLUS_REFUND = 'refund';
    const WEBPAYPLUS_STATUS = 'status';

    const ONECLICK_STATUS = 'status';
    const ONECLICK_START = 'start';
    const ONECLICK_FINISH = 'finish';
    const ONECLICK_REFUND = 'refund';
    const ONECLICK_AUTHORIZE = 'authorize';

    const SERVICE_GENERIC = 'generic';

    const PAYMENT_TYPE_CODE = [
        "VD" => "Venta Débito",
        "VN" => "Venta Normal",
        "VC" => "Venta en cuotas",
        "SI" => "3 cuotas sin interés",
        "S2" => "2 cuotas sin interés",
        "NC" => "N cuotas sin interés",
        "VP" => "Venta Prepago"
    ];

    const PAYMENT_TYPE_CREDIT = "Crédito";
    const PAYMENT_TYPE_DEBIT = "Débito";
    const PAYMENT_TYPE_PREPAID = "Prepago";

    const PAYMENT_TYPE = [
        "VD" => self::PAYMENT_TYPE_DEBIT,
        "VN" => self::PAYMENT_TYPE_CREDIT,
        "VC" => self::PAYMENT_TYPE_CREDIT,
        "SI" => self::PAYMENT_TYPE_CREDIT,
        "S2" => self::PAYMENT_TYPE_CREDIT,
        "NC" => self::PAYMENT_TYPE_CREDIT,
        "VP" => self::PAYMENT_TYPE_PREPAID
    ];

    const INSTALLMENT_TYPE = [
        "VC" => "Venta en cuotas",
        "SI" => "3 cuotas sin interés",
        "S2" => "2 cuotas sin interés",
        "NC" => "N cuotas sin interés"
    ];

    const STATUS_DESCRIPTION =  [
        'INITIALIZED' => 'Inicializada',
        'AUTHORIZED' => 'Autorizada',
        'REVERSED' => 'Reversada',
        'FAILED' => 'Fallida',
        'NULLIFIED' => 'Anulada',
        'PARTIALLY_NULLIFIED' => 'Parcialmente anulada',
        'CAPTURED' => 'Capturada',
    ];

    const ECOMMERCE_WOOCOMMERCE = 'woocommerce';

    const REPO_WOOCOMMERCE = 'TransbankDevelopers/transbank-plugin-woocommerce-webpay-rest';
    const REPO_OFFICIAL_WOOCOMMERCE = 'woocommerce/woocommerce';

    const RETURN_URL_PARAM = 'plugin';
}
