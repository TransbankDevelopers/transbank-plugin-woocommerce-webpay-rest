<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\Plugin\Exceptions\EcommerceException;

final class RequestInputHelper
{
    private const IDENTIFIER_PATTERN = '/^[A-Za-z0-9:_-]+$/';
    private const MAX_IDENTIFIER_LENGTH = 255;

    public static function resolveRequestMethod(array $server): string
    {
        $requestMethod = sanitize_text_field($server['REQUEST_METHOD'] ?? '');

        if ($requestMethod === '') {
            return 'GET';
        }

        return $requestMethod;
    }

    public static function sanitizeExpectedFields(array $request, array $fields): array
    {
        $sanitized = [];

        foreach ($fields as $field) {
            $sanitized[$field] = sanitize_text_field((string) ($request[$field] ?? ''));
        }

        return $sanitized;
    }

    public static function hasValue(?string $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    public static function assertValidIdentifier(?string $value, string $fieldName): void
    {
        if (!self::hasValue($value)) {
            throw new EcommerceException("Parámetro inválido recibido: {$fieldName}");
        }

        if (strlen($value) > self::MAX_IDENTIFIER_LENGTH || !preg_match(self::IDENTIFIER_PATTERN, $value)) {
            throw new EcommerceException("Formato inválido recibido para: {$fieldName}");
        }
    }
}
