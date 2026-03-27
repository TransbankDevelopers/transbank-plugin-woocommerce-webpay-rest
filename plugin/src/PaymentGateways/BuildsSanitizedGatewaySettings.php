<?php

namespace Transbank\WooCommerce\WebpayRest\PaymentGateways;

trait BuildsSanitizedGatewaySettings
{
    /**
     * Builds the sanitized gateway settings array using WooCommerce field sanitization.
     *
     * @param array $postData Raw form payload.
     * @return array<string, mixed>
     */
    protected function buildSanitizedGatewaySettings(array $postData): array
    {
        $settings = [];

        foreach ($this->get_form_fields() as $key => $field) {
            if ('title' === $this->get_field_type($field)) {
                continue;
            }

            $settings[$key] = $this->get_field_value($key, $field, $postData);
        }

        return $settings;
    }
}
