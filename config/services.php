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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ultramsg' => [
        'instance' => env('ULTRAMSG_INSTANCE'),
        'token' => env('ULTRAMSG_TOKEN'),
        'base_url' => env('ULTRAMSG_BASE_URL', 'https://api.ultramsg.com'),
        'ticket_image' => env('ULTRAMSG_TICKET_IMAGE', rtrim(env('DEBOLETO_NET_URL', 'https://deboleto.net'), '/').'/images/boletodigital.jpg'),
        'bond_image' => env('ULTRAMSG_BOND_IMAGE', rtrim(env('DEBOLETO_NET_URL', 'https://deboleto.net'), '/').'/images/bonos/bonos2026.jpg'),
        'country_code' => env('ULTRAMSG_COUNTRY_CODE', '+52'),
    ],

    'stytch' => [
        'project_id' => env('STYTCH_PROJECT_ID'),
        'secret' => env('STYTCH_SECRET'),
        'base_url' => env('STYTCH_BASE_URL', 'https://api.stytch.com/v1'),
        'code_minutes' => 5,
    ],

    'openpay' => [
        'merchant_id' => env('OPENPAY_MERCHANT_ID'),
        'private_key' => env('OPENPAY_PRIVATE_KEY'),
        'public_key' => env('OPENPAY_PUBLIC_KEY'),
        'production' => (bool) env('OPENPAY_PRODUCTION', false),
        'webhook_user' => env('OPENPAY_WEBHOOK_USER'),
        'webhook_password' => env('OPENPAY_WEBHOOK_PASSWORD'),
    ],

    'facebook' => [
        'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v21.0'),
    ],

    'ligamx' => [
        'url' => env('LIGA_MX_URL'),
        'contact_list' => env('LIGA_ID'),
    ],

];
