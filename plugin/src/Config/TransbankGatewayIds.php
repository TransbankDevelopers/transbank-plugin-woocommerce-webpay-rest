<?php

namespace Transbank\WooCommerce\WebpayRest\Config;

/**
 * Canonical identifiers for Transbank WooCommerce gateways.
 *
 * These constants represent the gateway IDs as registered in WooCommerce and
 * are used consistently across:
 * - Settings storage (`woocommerce_{gatewayId}_settings`)
 * - Configuration factories
 * - Gateway resolution helpers
 *
 * Using constants avoids string duplication and prevents subtle typos when
 * referencing gateway identifiers across the codebase.
 */
final class TransbankGatewayIds
{
    /**
     * Webpay Plus REST gateway identifier.
     */
    public const WEBPAY_PLUS_REST = 'transbank_webpay_plus_rest';

    /**
     * Webpay Oneclick Mall REST gateway identifier.
     */
    public const ONECLICK_MALL_REST = 'transbank_oneclick_mall_rest';
}
