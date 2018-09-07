<?php

return [

    /**
     *Debug
     */
    'debug' => env('SIGNATURE_DEBUG', true),
    // default signer
    'default' => env('SIGNATURE_DRIVER', 'hmac'),
    'hmac' => [
        'driver' => 'HMAC',
        'options' => [
            // default algo
            'algo' => env('SIGNATURE_HMAC_ALGO', 'sha1'),
            // default hmac key
            'key' => env('SIGNATURE_HMAC_KEY'),
        ],
    ],
    'rsa' => [
        'driver' => 'RSA',
        'options' => [
            //default algo
            'algo' => env('SIGNATURE_RSA_ALGO', 'sha1'),
            // default primary key (if file should be absolute address)
            'publicKey' => env('SIGNATURE_RSA_PUBLIC_KEY'),
            // default primary key (if file should be absolute address)
            'privateKey' => env('SIGNATURE_RSA_PRIVATE_KEY'),
        ],
    ],
];
