<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'razorpay' => [
        'key' => env('RAZORPAY_KEY'),
        'secret' => env('RAZORPAY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],
    'mailchimp' => [
        'key' => env('MAILCHIMP_API_KEY'),
        'server' => env('MAILCHIMP_SERVER'),
        'audience' => env('MAILCHIMP_AUDIENCE_ID'),
    ],
    'woocommerce' => [
        'url' => env('WOOCOMMERCE_STORE_URL', 'https://grassflorist.com'),
        'consumer_key' => env('WOOCOMMERCE_CONSUMER_KEY'),
        'consumer_secret' => env('WOOCOMMERCE_CONSUMER_SECRET'),
        'verify_ssl' => env('WOOCOMMERCE_VERIFY_SSL', false),
    ],
    'msegat' => [
        'username' => env('MSEGAT_USERNAME'),
        'api_key' => env('MSEGAT_API_KEY'),
        'sender' => env('MSEGAT_USER_SENDER', 'Grass'),
    ],
];
