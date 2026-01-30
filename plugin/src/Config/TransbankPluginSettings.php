<?php

namespace Transbank\WooCommerce\WebpayRest\Config;

/**
 * Plugin-wide settings wrapper.
 *
 * Reads and writes the base plugin configuration from the WordPress options table using:
 * - `transbank_webpay_settings`
 *
 * Key features:
 * - Canonical option storage in a single array option.
 * - Legacy/split options supported as read fallback for backward compatibility.
 * - Cached reads per request.
 * - Filtered runtime view via `transbank_plugin_settings_all`.
 *
 * Important:
 * - If both canonical values and legacy values exist, canonical values take precedence.
 * - Writes persist only canonical values (legacy options are not written back).
 */
final class TransbankPluginSettings
{
    public const OPTION_NAME = 'transbank_webpay_settings';

    public const KEY_REVIEW_NOTICE_DISMISSED = 'review_notice_dismissed';
    public const KEY_WELCOME_MESSAGES = 'welcome_messages';
    public const KEY_LOGGING = 'logging';

    public const KEY_LOGGING_ENABLED = 'enabled';
    public const KEY_LOGGING_LEVEL = 'level';

    public const LEGACY_LOGGING_LEVEL_OPTION = 'transbank_webpay_settings_logging_level';

    public const LEGACY_WELCOME_WEBPAY_SUFFIX = '_showed_welcome_message';
    public const LEGACY_WELCOME_ONECLICK_SUFFIX = '_showed_welcome_message';

    private ?array $cache = null;

    /**
     * Returns the filtered (runtime) settings array.
     *
     * Applies `transbank_plugin_settings_all` to allow external customization.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $raw = get_option(self::OPTION_NAME, []);

        if (!is_array($raw)) {
            $raw = [];
        }

        $settings = wp_parse_args($raw, $this->getDefaults());

        $settings = apply_filters('transbank_plugin_settings_all', $settings);

        $this->cache = $settings;

        return $this->cache;
    }

    /**
     * Checks whether the review notice has been dismissed.
     *
     * @return bool
     */
    public function isReviewNoticeDismissed(): bool
    {
        $settings = $this->getAll();

        return (bool) ($settings[self::KEY_REVIEW_NOTICE_DISMISSED] ?? false);
    }

    /**
     * Sets the review notice dismissed flag.
     *
     * Persists under the canonical array option.
     *
     * @param bool $dismissed
     */
    public function setReviewNoticeDismissed(bool $dismissed): void
    {
        $raw = $this->loadRaw();

        $raw[self::KEY_REVIEW_NOTICE_DISMISSED] = $dismissed;

        $this->cache = null;

        update_option(self::OPTION_NAME, $this->normalizeForStorage($raw));
    }

    /**
     * Checks whether the welcome message has been shown for a given gateway key.
     *
     * @param string $gatewayKey Expected keys: `webpay_rest`, `oneclick_rest`.
     * @return bool
     */
    public function isWelcomeMessageShown(string $gatewayKey): bool
    {
        $settings = $this->getAll();

        if (!isset($settings[self::KEY_WELCOME_MESSAGES][$gatewayKey])) {
            return false;
        }

        return (bool) $settings[self::KEY_WELCOME_MESSAGES][$gatewayKey];
    }

    /**
     * Sets the welcome message shown flag for a given gateway key.
     *
     * Persists under the canonical array option.
     *
     * @param string $gatewayKey Expected keys: `webpay_rest`, `oneclick_rest`.
     * @param bool $shown
     */
    public function setWelcomeMessageShown(string $gatewayKey, bool $shown): void
    {
        $raw = $this->loadRaw();

        if (!isset($raw[self::KEY_WELCOME_MESSAGES]) || !is_array($raw[self::KEY_WELCOME_MESSAGES])) {
            $raw[self::KEY_WELCOME_MESSAGES] = [];
        }

        $raw[self::KEY_WELCOME_MESSAGES][$gatewayKey] = $shown;

        $this->cache = null;

        update_option(self::OPTION_NAME, $this->normalizeForStorage($raw));
    }

    /**
     * @return bool True if plugin logging is enabled.
     */
    public function isLoggingEnabled(): bool
    {
        $settings = $this->getAll();

        return (bool) ($settings[self::KEY_LOGGING][self::KEY_LOGGING_ENABLED] ?? true);
    }

    /**
     * Enables or disables plugin logging.
     *
     * Persists under the canonical array option.
     *
     * @param bool $enabled
     */
    public function setLoggingEnabled(bool $enabled): void
    {
        $raw = $this->loadRaw();

        if (!isset($raw[self::KEY_LOGGING]) || !is_array($raw[self::KEY_LOGGING])) {
            $raw[self::KEY_LOGGING] = [];
        }

        $raw[self::KEY_LOGGING][self::KEY_LOGGING_ENABLED] = $enabled;

        $this->cache = null;

        update_option(self::OPTION_NAME, $this->normalizeForStorage($raw));
    }

    /**
     * @return string Logging level (e.g. `info`, `debug`, `error`).
     */
    public function getLogLevel(): string
    {
        $settings = $this->getAll();

        $level = $settings[self::KEY_LOGGING][self::KEY_LOGGING_LEVEL] ?? 'info';

        return (string) $level;
    }

    /**
     * Sets the plugin log level.
     *
     * Persists under the canonical array option.
     *
     * @param string $level Expected values: `debug`, `info`, `warning`, `error`.
     */
    public function setLogLevel(string $level): void
    {
        $raw = $this->loadRaw();

        if (!isset($raw[self::KEY_LOGGING]) || !is_array($raw[self::KEY_LOGGING])) {
            $raw[self::KEY_LOGGING] = [];
        }

        $raw[self::KEY_LOGGING][self::KEY_LOGGING_LEVEL] = $this->sanitizeLogLevel($level);

        $this->cache = null;

        update_option(self::OPTION_NAME, $this->normalizeForStorage($raw));
    }

    /**
     * Clears the internal cache.
     *
     * Useful when settings are updated externally and you need a fresh read.
     */
    public function refresh(): void
    {
        $this->cache = null;
    }

    /**
     * Loads the canonical settings array without applying filters.
     *
     * - Reads the canonical option.
     * - Merges with defaults (including legacy fallbacks).
     *
     * @return array<string, mixed>
     */
    private function loadRaw(): array
    {
        $raw = get_option(self::OPTION_NAME, []);

        if (!is_array($raw)) {
            $raw = [];
        }

        return wp_parse_args($raw, $this->getDefaults());
    }

    /**
     * Canonical defaults for plugin settings.
     *
     * Legacy/split options are used as fallback values to keep backward compatibility.
     *
     * @return array<string, mixed>
     */
    private function getDefaults(): array
    {
        return [
            self::KEY_REVIEW_NOTICE_DISMISSED => false,
            self::KEY_WELCOME_MESSAGES => [
                TransbankGatewayIds::WEBPAY_PLUS_REST => $this->getLegacyWelcomeMessageShown(TransbankGatewayIds::WEBPAY_PLUS_REST),
                TransbankGatewayIds::ONECLICK_MALL_REST => $this->getLegacyWelcomeMessageShown(TransbankGatewayIds::ONECLICK_MALL_REST),
            ],
            self::KEY_LOGGING => [
                self::KEY_LOGGING_ENABLED => true,
                self::KEY_LOGGING_LEVEL => $this->getLegacyLogLevel(),
            ],
        ];
    }

    /**
     * Produces the canonical, persistable settings array.
     *
     * Ensures:
     * - Only canonical keys are stored.
     * - Missing keys are filled with defaults.
     * - Basic sanitization for known fields.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function normalizeForStorage(array $settings): array
    {
        $defaults = $this->getDefaults();

        $notice = $settings[self::KEY_REVIEW_NOTICE_DISMISSED] ?? $defaults[self::KEY_REVIEW_NOTICE_DISMISSED];

        $welcome = $settings[self::KEY_WELCOME_MESSAGES] ?? $defaults[self::KEY_WELCOME_MESSAGES];
        if (!is_array($welcome)) {
            $welcome = $defaults[self::KEY_WELCOME_MESSAGES];
        }

        $logging = $settings[self::KEY_LOGGING] ?? $defaults[self::KEY_LOGGING];
        if (!is_array($logging)) {
            $logging = $defaults[self::KEY_LOGGING];
        }

        $storedWelcome = [
            TransbankGatewayIds::WEBPAY_PLUS_REST => (bool) ($welcome[TransbankGatewayIds::WEBPAY_PLUS_REST] ?? $defaults[self::KEY_WELCOME_MESSAGES][TransbankGatewayIds::WEBPAY_PLUS_REST]),
            TransbankGatewayIds::ONECLICK_MALL_REST => (bool) ($welcome[TransbankGatewayIds::ONECLICK_MALL_REST] ?? $defaults[self::KEY_WELCOME_MESSAGES][TransbankGatewayIds::ONECLICK_MALL_REST]),
        ];

        $storedLogging = [
            self::KEY_LOGGING_ENABLED => (bool) ($logging[self::KEY_LOGGING_ENABLED] ?? $defaults[self::KEY_LOGGING][self::KEY_LOGGING_ENABLED]),
            self::KEY_LOGGING_LEVEL => $this->sanitizeLogLevel($logging[self::KEY_LOGGING_LEVEL] ?? $defaults[self::KEY_LOGGING][self::KEY_LOGGING_LEVEL]),
        ];

        return [
            self::KEY_REVIEW_NOTICE_DISMISSED => (bool) $notice,
            self::KEY_WELCOME_MESSAGES => $storedWelcome,
            self::KEY_LOGGING => $storedLogging,
        ];
    }

    /**
     * Sanitizes the log level to a safe, expected value.
     *
     * @param mixed $level
     * @return string
     */
    private function sanitizeLogLevel(mixed $level): string
    {
        $value = strtolower((string) $level);
        $allowed = ['debug', 'info', 'warning', 'error'];

        return in_array($value, $allowed, true) ? $value : 'info';
    }

    /**
     * Legacy fallback for welcome message flags.
     *
     * @param string $gatewayId Gateway id constant (e.g. `transbank_webpay_plus_rest`).
     * @return bool
     */
    private function getLegacyWelcomeMessageShown(string $gatewayId): bool
    {
        $optionName = $gatewayId . self::LEGACY_WELCOME_WEBPAY_SUFFIX;

        return (bool)get_option($optionName, false);
    }

    /**
     * Legacy fallback for log level.
     *
     * @return string
     */
    private function getLegacyLogLevel(): string
    {
        $level = get_option(self::LEGACY_LOGGING_LEVEL_OPTION, 'info');

        return (string) $level;
    }
}
