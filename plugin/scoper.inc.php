<?php

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

$vendorDir = __DIR__ . '/vendor';

return [
    'prefix' => 'TransbankVendor',
    'output-dir' => __DIR__ . '/vendor-prefixed',
    'exclude-namespaces' => [
        'Composer',
    ],
    'finders' => [
        Finder::create()
            ->files()
            ->in($vendorDir),
    ],
    'exclude-files' => [
        'vendor/bin/*',
    ],
];
