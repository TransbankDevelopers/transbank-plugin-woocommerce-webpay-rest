<?php

namespace Transbank\WooCommerce\WebpayRest\Utils;

class Template {
    private const TEMPLATE_DIR = '/templates/';

    private static function getTemplatePath(): string
    {
        return dirname(dirname(__DIR__)) . self::TEMPLATE_DIR;
    }

    public function render(string $name, array $parameters): void {
        wc_get_template($name, $parameters, null, self::getTemplatePath());
    }
}
