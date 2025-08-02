<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Zuma API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration settings for the Zuma payment
    | platform integration.
    |
    */

    'base_url' => env('ZUMA_BASE_URL', 'https://api.zuma.com'),

    'username' => env('ZUMA_USERNAME', ''),

    'password' => env('ZUMA_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout in seconds for API requests.
    |
    */
    'timeout' => env('ZUMA_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | SSL Verification
    |--------------------------------------------------------------------------
    |
    | Whether to verify SSL certificates. Set to false only in development.
    |
    */
    'verify_ssl' => env('ZUMA_VERIFY_SSL', true),

    /*
    |--------------------------------------------------------------------------
    | Token TTL (Time To Live)
    |--------------------------------------------------------------------------
    |
    | The lifetime in seconds for cached authentication tokens.
    | Default is 3600 seconds (1 hour).
    |
    */
    'token_ttl' => env('ZUMA_TOKEN_TTL', 3600),
];