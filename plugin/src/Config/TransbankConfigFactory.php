<?php

namespace Transbank\WooCommerce\WebpayRest\Config;

/**
 * Central factory and in-memory registry for Transbank configuration objects.
 *
 * Responsibilities:
 * - Lazily instantiates configuration objects.
 * - Ensures a single instance per request (lightweight singleton behavior).
 * - Centralizes configuration object creation to avoid scattering `new` calls.
 *
 * Lifecycle:
 * - Instances live for the duration of the PHP request.
 * - `reset()` can be used in tests or edge cases to force a fresh state.
 */
final class TransbankConfigFactory
{
    /**
     * Cached plugin-wide settings instance.
     */
    private static ?TransbankPluginSettings $pluginSettings = null;

    /**
     * Cached gateway settings instances indexed by gateway ID.
     *
     * @var array<string, TransbankGatewaySettings>
     */
    private static array $gatewaySettings = [];

    /**
     * Returns the plugin-wide settings instance.
     *
     * Lazily creates the instance on first access and reuses it afterwards.
     *
     * @return TransbankPluginSettings
     */
    public static function plugin(): TransbankPluginSettings
    {
        if (self::$pluginSettings === null) {
            self::$pluginSettings = new TransbankPluginSettings();
        }

        return self::$pluginSettings;
    }

    /**
     * Returns a gateway-specific settings instance.
     *
     * Ensures there is exactly one settings instance per gateway ID during
     * the request lifecycle.
     *
     * @param string $gatewayId One of the constants defined in {@see TransbankGatewayIds}.
     * @return TransbankGatewaySettings
     */
    public static function gateway(string $gatewayId): TransbankGatewaySettings
    {
        if (!isset(self::$gatewaySettings[$gatewayId])) {
            self::$gatewaySettings[$gatewayId] = new TransbankGatewaySettings($gatewayId);
        }

        return self::$gatewaySettings[$gatewayId];
    }

    /**
     * Resets all cached configuration instances.
     *
     * Intended primarily for:
     * - Unit tests
     * - Integration tests
     * - Scenarios where options are programmatically changed mid-request
     */
    public static function reset(): void
    {
        self::$pluginSettings = null;
        self::$gatewaySettings = [];
    }
}
