<?php

declare(strict_types=1);

return [
    'runtime_psr4' => [
        'TransbankVendor\\Transbank\\' => 'transbank/transbank-sdk/src/',
        'TransbankVendor\\Monolog\\' => 'monolog/monolog/src/Monolog/',
        'TransbankVendor\\Psr\\Log\\' => 'psr/log/src/',
        'TransbankVendor\\Psr\\Http\\Message\\' => 'psr/http-message/src/',
        'TransbankVendor\\Psr\\Http\\Client\\' => 'psr/http-client/src/',
        'TransbankVendor\\Psr\\Http\\Factory\\' => 'psr/http-factory/src/',
        'TransbankVendor\\GuzzleHttp\\Psr7\\' => 'guzzlehttp/psr7/src/',
        'TransbankVendor\\GuzzleHttp\\Promise\\' => 'guzzlehttp/promises/src/',
        'TransbankVendor\\GuzzleHttp\\' => 'guzzlehttp/guzzle/src/',
    ],
    'autoload_replacements' => [
        "'Transbank\\\\' =>" => "'TransbankVendor\\\\Transbank\\\\' =>",
        "'Monolog\\\\' =>" => "'TransbankVendor\\\\Monolog\\\\' =>",
        "'Psr\\\\Log\\\\' =>" => "'TransbankVendor\\\\Psr\\\\Log\\\\' =>",
        "'Psr\\\\Http\\\\Message\\\\' =>" => "'TransbankVendor\\\\Psr\\\\Http\\\\Message\\\\' =>",
        "'Psr\\\\Http\\\\Client\\\\' =>" => "'TransbankVendor\\\\Psr\\\\Http\\\\Client\\\\' =>",
        "'Psr\\\\Http\\\\Factory\\\\' =>" => "'TransbankVendor\\\\Psr\\\\Http\\\\Factory\\\\' =>",
        "'GuzzleHttp\\\\Psr7\\\\' =>" => "'TransbankVendor\\\\GuzzleHttp\\\\Psr7\\\\' =>",
        "'GuzzleHttp\\\\Promise\\\\' =>" => "'TransbankVendor\\\\GuzzleHttp\\\\Promise\\\\' =>",
        "'GuzzleHttp\\\\' =>" => "'TransbankVendor\\\\GuzzleHttp\\\\' =>",
    ],
    'code_replacement_patterns' => [
        '/(?<!TransbankVendor\\\\)\\\\?Transbank\\\\Webpay\\\\/' => 'TransbankVendor\\\\Transbank\\\\Webpay\\\\',
        '/(?<!TransbankVendor\\\\)\\\\?Transbank\\\\PatpassComercio\\\\/' => 'TransbankVendor\\\\Transbank\\\\PatpassComercio\\\\',
        '/(?<!TransbankVendor\\\\)\\\\?Monolog\\\\/' => 'TransbankVendor\\\\Monolog\\\\',
        '/(?<!TransbankVendor\\\\)\\\\?Psr\\\\Log\\\\/' => 'TransbankVendor\\\\Psr\\\\Log\\\\',
        '/(?<!TransbankVendor\\\\)\\\\?Psr\\\\Http\\\\/' => 'TransbankVendor\\\\Psr\\\\Http\\\\',
        '/(?<!TransbankVendor\\\\)\\\\?GuzzleHttp\\\\/' => 'TransbankVendor\\\\GuzzleHttp\\\\',

        '/(?<!TransbankVendor\\\\\\\\)Transbank\\\\\\\\Webpay\\\\\\\\/' => 'TransbankVendor\\\\\\\\Transbank\\\\\\\\Webpay\\\\\\\\',
        '/(?<!TransbankVendor\\\\\\\\)Transbank\\\\\\\\PatpassComercio\\\\\\\\/' => 'TransbankVendor\\\\\\\\Transbank\\\\\\\\PatpassComercio\\\\\\\\',
        '/(?<!TransbankVendor\\\\\\\\)Monolog\\\\\\\\/' => 'TransbankVendor\\\\\\\\Monolog\\\\\\\\',
        '/(?<!TransbankVendor\\\\\\\\)Psr\\\\\\\\Log\\\\\\\\/' => 'TransbankVendor\\\\\\\\Psr\\\\\\\\Log\\\\\\\\',
        '/(?<!TransbankVendor\\\\\\\\)Psr\\\\\\\\Http\\\\\\\\/' => 'TransbankVendor\\\\\\\\Psr\\\\\\\\Http\\\\\\\\',
        '/(?<!TransbankVendor\\\\\\\\)GuzzleHttp\\\\\\\\/' => 'TransbankVendor\\\\\\\\GuzzleHttp\\\\\\\\',
    ],
];
