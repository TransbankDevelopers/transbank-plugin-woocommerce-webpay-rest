<?php

namespace Transbank\WooCommerce\WebpayRest\Utils;

class Template {
    const TEMPLATE_PATH = WP_PLUGIN_DIR.'/transbank-webpay-plus-rest/templates/';
    public function render(string $name, array $parameters): void {
        wc_get_template( $name, $parameters, null, self::TEMPLATE_PATH );
    }
}
