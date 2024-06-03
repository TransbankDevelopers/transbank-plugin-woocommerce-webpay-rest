<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;

use Transbank\Webpay\Options;

class MaskData
{

    private const INDEXED_ARRAY = 0;
    private const ASSOCIATIVE_ARRAY = 1;
    private const OBJECT = 2;
    private const NO_ITERABLE = 3;
    private $keysToMask = [
        'child_commerce_code' => 'mask',
        'parentBuyOrder' => 'maskBuyOrder',
        'childBuyOrder' => 'maskBuyOrder',
        'username' => 'mask',
        'buyOrder' => 'maskBuyOrder',
        'commerceCode' => 'mask',
        'commerce_code' => 'mask',
        'email' => 'maskEmail',
        'tbkUser' => 'maskWithFormat',
        'buy_order' => 'maskBuyOrder',
        'session_id' => 'maskSessionId',
        'TBK_ORDEN_COMPRA' => 'maskBuyOrder',
        'TBK_ID_SESION' => 'maskSessionId',
        'params' => 'mask',
        'sessionId' => 'maskSessionId'
    ];

    protected $isIntegration;
    protected $log;

    public function __construct($environment)
    {
        $this->isIntegration = $environment == Options::ENVIRONMENT_INTEGRATION;
        $this->log = TbkFactory::createLogger();
    }

    /**
     * Mask a string who contains substrings separated by '-'.
     * The first and last substring are maintained. Others are masked.
     *
     * @param string $input a string to be masked.
     * @return string a string with substrings masked.
     */
    private function maskWithFormat($input)
    {
        return preg_replace_callback('/(?<=-).+?(?=-)/', function ($matches) {
            return str_repeat('x', strlen($matches[0]));
        }, $input);
    }

    /**
     * Mask an input data, replacing some characters by 'x'.
     *
     * @param string $input data to be masked.
     * @param string $pattern the pattern to maintain from original data.
     * @param int $charsToKeep number of original chars to keep at start and end.
     * @return string a string masked.
     */
    private function mask($input, $pattern = null, $charsToKeep = 4)
    {
        if(is_null($input)) {
            return '';
        }

        $len = strlen($input);

        if ($pattern != null) {
            $patternPos = strpos($input, $pattern);
            if ($patternPos === 0) {
                $startString = $pattern;
            } else {
                $endString = $pattern;
            }
        }

        $startString = $startString ?? substr($input, 0, $charsToKeep);
        $endString = $endString ?? substr($input, -$charsToKeep, $charsToKeep);
        $charsToReplace = $len - (strlen($startString) + strlen($endString));
        $replaceString = str_repeat("x", $charsToReplace);
        return $startString . $replaceString . $endString;
    }

    /**
     * Mask an email, and maintain the domain.
     *
     * @param string $email An email to be masked.
     * @return string email masked.
     */
    private function maskEmail($email)
    {
        return preg_replace_callback('/^(.{1,4})[^@]*(@.*)$/', function ($match) {
            return $match[1] . str_repeat('x', strlen($match[0]) - strlen($match[1]) - strlen($match[2])) . $match[2];
        }, $email);
    }

    /**
     * Mask an input string maintaining a start pattern like wc:`pattern`.
     *
     * @param string $input An string to be masked.
     * @param string $pattern A pattern to maintain, like `child` or `sessionId`.
     * @return string input masked.
     */
    private function maskWithPattern($input, $pattern)
    {
        $regexPattern = "/(wc:($pattern:)?\w{2})\w+:(\w{2})/";

        return preg_replace_callback($regexPattern, function ($matches) use ($input) {
            $prefix = $matches[1];
            $suffix = $matches[3];
            $maskLength = strlen($input) - strlen($prefix) - strlen($suffix) - 1;
            return $prefix . str_repeat('x', $maskLength) . $suffix;
        }, $input);
    }

    /**
     * Mask a string with buy order format.
     * If buy order starts with 'wc:child:', this will be maintained.
     *
     * @param string $buyOrder An string with buy order to mask.
     * @return string buy order masked.
     */
    public function maskBuyOrder($buyOrder)
    {
        if ($this->isIntegration) {
            return $buyOrder;
        }

        $pattern = 'child';
        return $this->maskWithPattern($buyOrder, $pattern);
    }

    /**
     * Mask a string with session id format.
     * If session id starts with 'wc:sessionId:', this will be maintained.
     *
     * @param string $sessionId An string with session id to mask.
     * @return string session id masked.
     */
    public function maskSessionId($sessionId)
    {
        if ($this->isIntegration) {
            return $sessionId;
        }

        $sessionIdPattern = 'sessionId';
        return $this->maskWithPattern($sessionId, $sessionIdPattern);
    }

    /**
     * Determine if an array is associative
     *
     * @param array $array an array to evaluate
     * @return boolean `true` if is associative, `false` otherwise
     */
    private function isAssociative($array)
    {
        return (bool)count(array_filter(array_keys($array), 'is_string'));
    }

    /**
     * Mask necessary fields from input when environment is production
     * If environment is production then original value is returned
     *
     * @param array $data An array containing data to mask.
     * @return array copy of input, with fields masked.
     */
    public function maskData($data)
    {
        try {
            if ($this->isIntegration) {
                return $data;
            }
            $newData = $this->copyWithSubArray($data);
            foreach ($newData as $key => $value) {
                switch ($this->getValueType($value)) {
                    case $this::OBJECT:
                        $newData[$key] = $this->maskObject($value);
                        break;
                    case $this::ASSOCIATIVE_ARRAY:
                        $newData[$key] = $this->maskAssociativeArray($value);
                        break;
                    case $this::INDEXED_ARRAY:
                        $newData[$key] = $this->maskIndexedArray($value);
                        break;
                    default:
                        $maskedValue = $this->getMaskedValue($key, $value);
                        $newData[$key] = $maskedValue;
                        break;
                }
            }
            return $newData;
        } catch (\Exception $e) {
            $this->log->logError('Error on Mask Data: ' . $e->getMessage());
            return $data;
        }
    }

    /**
     * Copy input to a new array.
     * If some element is an object, then it's cloned.
     *
     * @param array $array An array to be copied.
     * @return array copy of input array.
     */
    private function copyWithSubArray($array)
    {
        $newArray = null;
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $clonedValue = array_map(
                    function ($item) {
                        return is_object($item) ? clone $item : $item;
                    },
                    $value
                );
                $newArray[$key] = $clonedValue;
            } else {
                $newArray[$key] = is_object($value) ? clone $value : $value;
            }
        }
        return $newArray;
    }

    /**
     * Mask a value if is needed.
     *
     * @param string $key the key to determine if value should be masked.
     * @param mixed $value the value to be masked.
     * @return mixed masked value if key exists, `false` otherwise.
     */
    private function getMaskedValue($key, $value)
    {
        $keyExists = array_key_exists($key, $this->keysToMask);

        if ($keyExists) {
            return call_user_func([$this, $this->keysToMask[$key]], $value);
        }

        return $value;
    }

    /**
     * Returns type of element to determine how iterate over this
     *
     * @param string $value to evaluate.
     * @return int a constant representing the type of element
     */
    private function getValueType($value)
    {
        $valueType = $this::NO_ITERABLE;

        if (is_object($value)) {
            $valueType = $this::OBJECT;
        }

        if (is_array($value)) {
            if ($this->isAssociative($value)) {
                $valueType = $this::ASSOCIATIVE_ARRAY;
            } else {
                $valueType = $this::INDEXED_ARRAY;
            }
        }

        return $valueType;
    }

    /**
     * Evaluates object attributes and mask this if necessary
     *
     * @param object $object a reference to an object to mask
     * @return object the object with masked attributes
     */
    private function maskObject($object)
    {
        foreach ($object as $detailKey => $detailValue) {
            $maskedValue = $this->getMaskedValue($detailKey, $detailValue);
            $object->$detailKey = $maskedValue;
        }
        return $object;
    }

    /**
     * Evaluate values of input array and mask this if necessary.
     *
     * @param array $array the array to mask.
     * @return array the array with masked values.
     */
    private function maskAssociativeArray($array)
    {
        foreach ($array as $detailKey => $detailValue) {
            $maskedValue = $this->getMaskedValue($detailKey, $detailValue);
            $array[$detailKey] = $maskedValue;
        }
        return $array;
    }

    /**
     * Evaluate each item of an indexed array, and mask this through necessary method.
     *
     * @param array $array the array to mask.
     * @return array the array with masked values.
     */
    private function maskIndexedArray($array)
    {
        foreach ($array as $detail) {
            if (is_object($detail)) {
                $detail = $this->maskObject($detail);
            } else {
                $detail = $this->maskAssociativeArray($array);
            }
        }
        return $array;
    }
}
