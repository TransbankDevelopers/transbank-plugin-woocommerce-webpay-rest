<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

class BuyOrderHelper {
    private const BUY_ORDER_MAX_LENGTH = 26;
    private const DEFAULT_RANDOM_LENGTH = 8;
    private const BYTES_TO_HEX_RATIO = 2;
    private const ORDER_ID_VARIABLE_PATTERN = '/\{orderId\}/i';

     /**
     * Generates a random hexadecimal string of a given length.
     *
     * @param int $length Desired length of the hexadecimal string.
     * @return string Random hexadecimal string.
     */
    private static function generateRandomComponent(int $length): string {
        return bin2hex(openssl_random_pseudo_bytes(intdiv($length, self::BYTES_TO_HEX_RATIO)));
    }

    /**
     * Counts the number of static characters in the format string.
     * Static characters are those outside of {random, length=X} and {orderId}.
     *
     * @param string $format The format string to analyze.
     * @return int The count of static characters.
     */
    private static function countStaticChars($format): int {
        $pattern = '/\{random, length=\d+\}|\{orderId\}/';
        return strlen(preg_replace($pattern, '', $format));
    }

    /**
     * Extracts the length value from a {random, length=X} placeholder in the format string.
     * If no length is found, returns the default random length.
     *
     * @param string $template The format string containing {random, length=X}.
     * @return int The extracted length value or the default length if not specified.
     */
    private static function extractRandomLength($template): int {
        if (preg_match('/\{random, length=(\d+)\}/', $template, $matches)) {
            return (int) $matches[1];
        }
        return self::DEFAULT_RANDOM_LENGTH;
    }
    
    /**
     * Generates a unique buy order string based on a given format.
     * The format should include {random, length=X} for a random component and {orderId} for the order ID.
     *
     * Example format: "wc-{random, length=10}-{orderId}"
     *
     * @param string $format The template string containing placeholders.
     * @param string $orderId The order ID to be inserted into the formatted string.
     * @return string A generated string where placeholders are replaced with actual values.
     */
    public static function generateFromFormat(
        string $format,
        string $orderId
    ): string {
        $staticChars = self::countStaticChars($format);
        $maxRandomLength = self::BUY_ORDER_MAX_LENGTH - ($staticChars + strlen($orderId));
        $randomLength = self::extractRandomLength($format);
        $random = self::generateRandomComponent(min($randomLength, $maxRandomLength));
        $formatWithOrderId = preg_replace(self::ORDER_ID_VARIABLE_PATTERN, $orderId, $format);
        return preg_replace('/\{random(?:, length=\d+)?\}/i', $random, $formatWithOrderId);
    }
    
    /**
     * Validates if a format string follows the expected pattern.
     *
     * - It may contain `{random, length=X}`, `{random}`, or neither.
     * - It must contain `{orderId}`.
     *
     * @param string $format The format string to validate.
     * @return bool True if the format is valid, false otherwise.
     */
    public static function isValidFormat(string $format): bool {
        if (!preg_match(self::ORDER_ID_VARIABLE_PATTERN, $format)) {
            return false;
        }
        $formatWithoutPlaceholders = preg_replace(self::ORDER_ID_VARIABLE_PATTERN, '', $format);
        $formatWithoutPlaceholders = preg_replace('/\{random(?:, length=\d+)?\}/i', '', $formatWithoutPlaceholders);
        if (!preg_match('/^[A-Za-z0-9\-_:]*$/', $formatWithoutPlaceholders)) {
            return false;
        }
        return true;
    }

}

