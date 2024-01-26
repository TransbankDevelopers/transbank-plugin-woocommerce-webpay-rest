<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

class BlocksHelper
{

    const ONECLICK_SUCCESSFULL_INSCRIPTION = 0;
    const ONECLICK_TIMEOUT = 1;
    const ONECLICK_WITHOUT_TOKEN = 2;
    const ONECLICK_USER_CANCELED = 3;
    const ONECLICK_INVALID_STATUS = 4;
    const ONECLICK_FINISH_ERROR = 5;
    const ONECLICK_REJECTED_INSCRIPTION = 6;
    const WEBPAY_APPROVED = 7;
    const WEBPAY_ALREADY_PAID = 8;
    const WEBPAY_INVALID_ORDER_STATUS = 9;
    const WEBPAY_TIMEOUT = 10;
    const WEBPAY_USER_CANCELED_ALREADY_PAID = 11;
    const WEBPAY_USER_CANCELED = 12;
    const WEBPAY_DOUBLE_TOKEN = 13;
    const WEBPAY_INVALID_STATUS = 14;
    const WEBPAY_REJECTED_COMMIT = 15;
    const WEBPAY_COMMIT_ERROR = 16;
    const WEBPAY_EXCEPTION = 17;

    public static function checkBlocksEnabled() {

        $checkout_page = wc_get_page_id('checkout');
        return has_block('woocommerce/checkout', $checkout_page);

    }

    public static function addLegacyNotices($message, $type) {
        if (self::checkBlocksEnabled()) {
            return;
        }
        wc_add_notice($message, $type);
    }

}
