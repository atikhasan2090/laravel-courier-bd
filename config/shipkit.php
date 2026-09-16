<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Courier Driver
    |--------------------------------------------------------------------------
    |
    | Supported options: "pathao", "redx", "steadfast"
    |
    */
    'default' => env('COURIER_DEFAULT_DRIVER', 'steadfast'),

    /*
    |--------------------------------------------------------------------------
    | Enabled Couriers
    |--------------------------------------------------------------------------
    |
    | Active couriers used during auto-comparison and fee calculations.
    |
    */
    'enabled_couriers' => ['pathao', 'redx', 'steadfast'],

    /*
    |--------------------------------------------------------------------------
    | Automatic Shipment Logging
    |--------------------------------------------------------------------------
    |
    | If true, shipments created through the package will automatically be saved
    | in the shipments database table.
    |
    */
    'auto_log' => env('COURIER_AUTO_LOG', true),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    */
    'webhooks' => [
        'prefix' => 'shipkit/webhooks',
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Courier Credentials & Configuration
    |--------------------------------------------------------------------------
    |
    */
    'couriers' => [

        'pathao' => [
            'sandbox' => env('PATHAO_SANDBOX', true),
            'client_id' => env('PATHAO_CLIENT_ID', ''),
            'client_secret' => env('PATHAO_CLIENT_SECRET', ''),
            'username' => env('PATHAO_USERNAME', ''),
            'password' => env('PATHAO_PASSWORD', ''),
            'store_id' => env('PATHAO_STORE_ID', 0),
            'webhook_secret' => env('PATHAO_WEBHOOK_SECRET', ''),
        ],

        'redx' => [
            'sandbox' => env('REDX_SANDBOX', true),
            'api_token' => env('REDX_API_TOKEN', ''),
            'webhook_secret' => env('REDX_WEBHOOK_SECRET', ''),
        ],

        'steadfast' => [
            'sandbox' => env('STEADFAST_SANDBOX', false),
            'api_key' => env('STEADFAST_API_KEY', ''),
            'secret_key' => env('STEADFAST_SECRET_KEY', ''),
            'webhook_secret' => env('STEADFAST_WEBHOOK_SECRET', ''),
        ],

    ],

];
