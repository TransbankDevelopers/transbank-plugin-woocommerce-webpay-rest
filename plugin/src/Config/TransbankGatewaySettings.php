<?php

namespace Transbank\WooCommerce\WebpayRest\Config;

/**
 * Gateway settings wrapper for WooCommerce payment methods.
 *
 * Reads and writes settings from the WordPress options table using the standard
 * WooCommerce option name format: `woocommerce_{gatewayId}_settings`.
 *
 * Key features:
 * - Canonical (normalized) keys are used internally and for persistence.
 * - Legacy keys are supported for read compatibility and mapped into canonical keys.
 * - One-level caching:
 *   - rawCache: normalized + defaults, without filters (used for persistence).
 * - Dirty tracking: `save()` only writes when changes were made through `set()`/`setMany()`.
 *
 * Important:
 * - If both canonical and legacy keys exist, canonical values take precedence.
 * - `save()` persists only canonical keys (legacy keys are not written back).
 * - `getPersisted()` / `getPersistedAll()` are the preferred read APIs for
 *   gateway settings because they preserve the distinction between missing,
 *   empty and configured values.
 */
final class TransbankGatewaySettings
{
    public const ENABLED = 'enabled';
    public const ENVIRONMENT = 'environment';
    public const COMMERCE_CODE = 'commerce_code';
    public const CHILD_COMMERCE_CODE = 'child_commerce_code';
    public const API_KEY = 'api_key';
    public const MAX_AMOUNT = 'max_amount';
    public const AFTER_PAYMENT_ORDER_STATUS = 'after_payment_order_status';
    public const DESCRIPTION = 'payment_gateway_description';
    public const BUY_ORDER_FORMAT = 'buy_order_format';
    public const CHILD_BUY_ORDER_FORMAT = 'child_buy_order_format';
    public const ALLOWED_AFTER_PAYMENT_ORDER_STATUSES = [
        '',
        'processing',
        'completed',
    ];

    private string $gatewayId;
    private ?array $persistedCache = null;
    private ?array $rawCache = null;
    private bool $dirty = false;

    /**
     * @param string $gatewayId WooCommerce gateway id (e.g. `transbank_webpay_plus_rest`).
     */
    public function __construct(string $gatewayId)
    {
        $this->gatewayId = $gatewayId;
    }

    /**
     * Returns only the persisted settings after legacy-key normalization.
     *
     * This method does not merge canonical defaults and preserves the
     * distinction between "missing", "empty", and "configured".
     *
     * @return array<string, mixed>
     */
    public function getPersistedAll(): array
    {
        return $this->loadPersisted();
    }

    /**
     * Gets a single persisted setting value using canonical keys.
     *
     * This method only reads values that actually exist in persisted settings
     * after legacy-key normalization. No canonical defaults are applied.
     *
     * @param string $key Canonical key (prefer using class constants).
     * @param mixed $default Value returned when the key does not exist.
     * @return mixed
     */
    public function getPersisted(string $key, mixed $default = null): mixed
    {
        $settings = $this->loadPersisted();

        if (!array_key_exists($key, $settings)) {
            return $default;
        }

        return $settings[$key];
    }

    /**
     * Gets a persisted string value constrained to an allowed domain.
     *
     * Returns the persisted value only when it is a string that matches one of
     * the allowed values exactly. Otherwise, returns the runtime fallback.
     *
     * @param string $key Canonical key (prefer using class constants).
     * @param string[] $allowedValues Valid domain values for this setting.
     * @param string $fallback Value returned when the persisted value is missing or invalid.
     */
    public function getPersistedAllowedValue(string $key, array $allowedValues, string $fallback): string
    {
        $value = $this->getPersisted($key);

        if (!is_string($value)) {
            return $fallback;
        }

        if (!in_array($value, $allowedValues, true)) {
            return $fallback;
        }

        return $value;
    }

    /**
     * Sets a single setting value (canonical key) into the raw cache.
     *
     * The value is sanitized and marked as dirty for persistence.
     *
     * @param string $key Canonical key (prefer using class constants).
     * @param mixed $value Value to set.
     */
    public function set(string $key, mixed $value): void
    {
        $raw = $this->loadRaw();

        $raw[$key] = $this->sanitize($key, $value);

        $this->persistedCache = null;
        $this->rawCache = $raw;
        $this->dirty = true;
    }

    /**
     * Batch setter for multiple values.
     *
     * @param array<string, mixed> $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value);
        }
    }

    /**
     * Persists the canonical settings to the WordPress options table.
     *
     * Only allowed canonical keys are written. Legacy keys are not persisted.
     * No-op if nothing changed since the last load/save.
     */
    public function save(): void
    {
        if (!$this->dirty) {
            return;
        }

        $raw = $this->loadRaw();
        $stored = $this->normalizeForStorage($raw);

        update_option($this->getOptionName(), $stored);

        $this->persistedCache = null;
        $this->rawCache = null;
        $this->dirty = false;
    }

    /**
     * Clears caches and resets dirty state.
     *
     * Useful when options are updated externally (e.g. WC admin settings save) and you need a fresh read.
     */
    public function refresh(): void
    {
        $this->persistedCache = null;
        $this->rawCache = null;
        $this->dirty = false;
    }

    /**
     * Loads raw settings (persistence view).
     *
     * Raw settings are:
     * - read from the option
     * - normalized (legacy -> canonical fallback)
     * - merged with defaults
     * - not filtered (safe for persistence)
     *
     * @return array<string, mixed>
     */
    private function loadRaw(): array
    {
        if ($this->rawCache !== null) {
            return $this->rawCache;
        }

        $raw = get_option($this->getOptionName(), []);

        if (!is_array($raw)) {
            $raw = [];
        }

        $normalized = $this->normalizeKeys($raw);

        $this->rawCache = wp_parse_args($normalized, $this->getDefaults());

        return $this->rawCache;
    }

    /**
     * Loads persisted settings without applying canonical defaults.
     *
     * Persisted settings are:
     * - read from the option
     * - normalized (legacy -> canonical fallback)
     * - not merged with defaults
     * - not filtered
     *
     * @return array<string, mixed>
     */
    private function loadPersisted(): array
    {
        if ($this->persistedCache !== null) {
            return $this->persistedCache;
        }

        $raw = get_option($this->getOptionName(), []);

        if (!is_array($raw)) {
            return [];
        }

        $this->persistedCache = $this->normalizeKeys($raw);

        return $this->persistedCache;
    }

    /**
     * @return string WordPress option name for this gateway settings array.
     */
    private function getOptionName(): string
    {
        return sprintf('woocommerce_%s_settings', $this->gatewayId);
    }

    /**
     * Canonical defaults for all supported keys.
     *
     * @return array<string, mixed>
     */
    private function getDefaults(): array
    {
        return [
            self::ENABLED => 'no',
            self::ENVIRONMENT => 'TEST',
            self::COMMERCE_CODE => '',
            self::CHILD_COMMERCE_CODE => '',
            self::API_KEY => '',
            self::MAX_AMOUNT => 0,
            self::AFTER_PAYMENT_ORDER_STATUS => '',
            self::DESCRIPTION => '',
            self::BUY_ORDER_FORMAT => '',
            self::CHILD_BUY_ORDER_FORMAT => '',
        ];
    }

    /**
     * List of canonical keys that are allowed to be persisted.
     *
     * @return string[]
     */
    private function getAllowedKeys(): array
    {
        return [
            self::ENABLED,
            self::ENVIRONMENT,
            self::COMMERCE_CODE,
            self::CHILD_COMMERCE_CODE,
            self::API_KEY,
            self::MAX_AMOUNT,
            self::AFTER_PAYMENT_ORDER_STATUS,
            self::DESCRIPTION,
            self::BUY_ORDER_FORMAT,
            self::CHILD_BUY_ORDER_FORMAT,
        ];
    }

    /**
     * Maps legacy keys into canonical keys, without overwriting canonical values.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private function normalizeKeys(array $raw): array
    {
        if (isset($raw['webpay_rest_environment']) && !isset($raw[self::ENVIRONMENT])) {
            $raw[self::ENVIRONMENT] = $raw['webpay_rest_environment'];
        }

        if (isset($raw['webpay_rest_commerce_code']) && !isset($raw[self::COMMERCE_CODE])) {
            $raw[self::COMMERCE_CODE] = $raw['webpay_rest_commerce_code'];
        }

        if (isset($raw['webpay_rest_api_key']) && !isset($raw[self::API_KEY])) {
            $raw[self::API_KEY] = $raw['webpay_rest_api_key'];
        }

        if (isset($raw['webpay_rest_after_payment_order_status']) && !isset($raw[self::AFTER_PAYMENT_ORDER_STATUS])) {
            $raw[self::AFTER_PAYMENT_ORDER_STATUS] = $raw['webpay_rest_after_payment_order_status'];
        }

        if (isset($raw['webpay_rest_payment_gateway_description']) && !isset($raw[self::DESCRIPTION])) {
            $raw[self::DESCRIPTION] = $raw['webpay_rest_payment_gateway_description'];
        }

        if (isset($raw['oneclick_after_payment_order_status']) && !isset($raw[self::AFTER_PAYMENT_ORDER_STATUS])) {
            $raw[self::AFTER_PAYMENT_ORDER_STATUS] = $raw['oneclick_after_payment_order_status'];
        }

        if (isset($raw['oneclick_payment_gateway_description']) && !isset($raw[self::DESCRIPTION])) {
            $raw[self::DESCRIPTION] = $raw['oneclick_payment_gateway_description'];
        }

        return $raw;
    }

    /**
     * Sanitizes values before storing them in the raw cache.
     *
     * @param string $key Canonical key.
     * @param mixed $value Raw value.
     * @return mixed Sanitized value.
     */
    private function sanitize(string $key, mixed $value): mixed
    {
        $result = $value;

        if ($key === self::ENABLED) {
            $result = $value === 'yes' ? 'yes' : 'no';
        } elseif ($key === self::ENVIRONMENT) {
            $env = strtoupper((string) $value);
            $result = in_array($env, ['TEST', 'LIVE'], true) ? $env : 'TEST';
        } elseif ($key === self::MAX_AMOUNT) {
            $intValue = (int) $value;
            $result = $intValue < 0 ? 0 : $intValue;
        } elseif (
            in_array($key, [
                self::COMMERCE_CODE,
                self::CHILD_COMMERCE_CODE,
                self::API_KEY,
                self::AFTER_PAYMENT_ORDER_STATUS,
                self::DESCRIPTION,
                self::BUY_ORDER_FORMAT,
                self::CHILD_BUY_ORDER_FORMAT,
            ], true)
        ) {
            $result = (string) $value;
        }

        return $result;
    }

    /**
     * Produces the canonical, persistable settings array.
     *
     * Ensures:
     * - only allowed canonical keys are included
     * - missing keys are filled with defaults
     *
     * @param array<string, mixed> $settings Raw canonical settings.
     * @return array<string, mixed> Persistable canonical settings.
     */
    private function normalizeForStorage(array $settings): array
    {
        $defaults = $this->getDefaults();
        $stored = [];

        foreach ($this->getAllowedKeys() as $key) {
            $stored[$key] = $settings[$key] ?? $defaults[$key];
        }

        return $stored;
    }
}
