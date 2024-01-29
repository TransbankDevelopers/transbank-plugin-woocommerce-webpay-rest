<?php

namespace Transbank\WooCommerce\WebpayRest\Helpers;
use Transbank\Webpay\Options;
class MaskData
{

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

    public function __construct($environment){
        $this->isIntegration = $environment == Options::ENVIRONMENT_INTEGRATION;
    }

    /**
     * Mask a string who contains substrings separated by '-'.
     * The first and last substring are maintained. Others are masked.
     *
     * @param string $input a string to be masked.
     * @return string a string with substrings masked.
     */
    private function maskWithFormat($input){
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
    private function mask($input, $pattern = null, $charsToKeep = 4 ){
        $len = strlen($input);

        if ( $pattern != null ) {
           $patternPos = strpos($input, $pattern);
           if ( $patternPos === 0 ) {
                $startString = $pattern;
            }
            else {
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
     * Obtain a substring with '@' and the email domain.
     *
     * @param string $email An email to be evaluated.
     * @return string a string with '@' and email domain.
     */
    private function getEmailPattern($email) {
        $pos = strpos($email, '@');
        $len = strlen($email);
        $since = $pos - $len;
        $to = $len - $pos;
        return substr($email, $since, $to);
    }

    /**
     * Mask an email, and maintain the domain.
     *
     * @param string $email An email to be masked.
     * @return string email masked.
     */
    private function maskEmail($email){
        $emailPattern = $this->getEmailPattern($email);
        return $this->mask($email, $emailPattern);
    }

    /**
     * Mask a string with buy order format.
     * If buy order starts with 'wc:child:', this will be maintained.
     * Otherwise, it will keep 6 original chars at start and end.
     * Returns buy order masked.
     *
     * @param string $sessionId An string with buy order to mask.
     * @return string buy order masked.
     */
    public function maskBuyOrder($buyOrder){
        if($this->isIntegration){
            return $buyOrder;
        }
        $parsedBuyOrder = $buyOrder;
        $charsToKeep = 6;
        $childOrdersPattern = 'wc:child:';
        $childPatternToKeep = str_contains($parsedBuyOrder, $childOrdersPattern) ? $childOrdersPattern : null;
        return $this->mask($parsedBuyOrder, $childPatternToKeep, $charsToKeep);
    }

    /**
     * Mask a string with session id format.
     * If session id starts with 'wc:sessionId:', this will be maintained.
     * Otherwise, it will keep 6 original chars at start and end.
     * Returns session id masked.
     *
     * @param string $sessionId An string with session id to mask.
     * @return string session id masked.
     */
    public function maskSessionId($sessionId){
        if($this->isIntegration){
            return $sessionId;
        }
        $charsToKeep = 6;
        $sessionIdPattern = 'wc:sessionId:';
        $sessionPatternToKeep = str_contains($sessionId, $sessionIdPattern) ? $sessionIdPattern : null;
        return $this->mask($sessionId, $sessionPatternToKeep, $charsToKeep);
    }

    private function isAssociative($array) {
        return (bool)count(array_filter(array_keys($array), 'is_string'));
    }

    /**
     * Mask necesary fields from input when environment is production
     * If environment is production then original value is returned
     *
     * @param array $data An array containing data to mask.
     * @return array copy of input, with fields masked.
     */
    public function maskData($data){
        if ($this->isIntegration){
            return $data;
        }
        $newData = $this->copyWithSubArray($data);
        foreach ($newData as $key => $value) {
            if(is_object($value)) {
                foreach($value as $detailKey => $detailValue) {
                    $maskedValue = $this->getMaskedValue($detailKey, $detailValue);
                    $newData[$key]->$detailKey = $maskedValue ? $maskedValue : $detailValue;
                }
            }
            else if (is_array($value)) {
                if ($this->isAssociative($value)) {
                    foreach($value as $detailKey => $detailValue) {
                        $maskedValue = $this->getMaskedValue($detailKey, $detailValue);
                        $newData[$key][$detailKey] = $maskedValue ? $maskedValue : $detailValue;
                    }
                }
                else {
                    foreach($value as $detail) {
                        foreach($detail as $detailKey => $detailValue) {
                            $maskedValue = $this->getMaskedValue($detailKey, $detailValue);
                            $detail->$detailKey = $maskedValue ? $maskedValue : $detailValue;
                        }
                    }
                }
            }
            else {
                $maskedValue = $this->getMaskedValue($key, $value);
                $newData[$key] = $maskedValue ? $maskedValue : $value;
            }
        }
        return $newData;
    }

    /**
     * Copy input to a new array.
     * If some element is an object, then it's cloned.
     * Returns a copy of input array.
     *
     * @param array $array An array to be copied.
     * @return array copy of input array.
     */
    private function copyWithSubArray($array){
        $newArray = null;
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $clonedValue = array_map(function ($item) {
                                            return is_object($item) ? clone $item : $item;
                                        },
                                        $value);
                $newArray[$key] = $clonedValue;
            }
            else {
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
    private function getMaskedValue($key, $value){
        $keyExists = array_key_exists($key, $this->keysToMask);
        if($keyExists){
            return call_user_func([$this, $this->keysToMask[$key]], $value);
        }
        return false;
    }

}
