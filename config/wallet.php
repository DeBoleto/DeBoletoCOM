<?php

return [

    // Nombre que aparece como emisor del pase en Apple Wallet y Google Wallet.
    'organization' => env('WALLET_ORGANIZATION', 'DeBoleto'),

    'colors' => [
        'background' => env('WALLET_BACKGROUND_COLOR', '#0b0f19'),
        'foreground' => env('WALLET_FOREGROUND_COLOR', '#ffffff'),
        'label' => env('WALLET_LABEL_COLOR', '#4ecba0'),
    ],

    // Apple Wallet: Pass Type ID y certificado (.p12) del Apple Developer Program,
    // más el certificado intermedio WWDR G4 en PEM.
    'apple' => [
        'enabled' => (bool) env('WALLET_APPLE_ENABLED', false),
        'pass_type_id' => env('WALLET_APPLE_PASS_TYPE_ID'),
        'team_id' => env('WALLET_APPLE_TEAM_ID'),
        'certificate' => env('WALLET_APPLE_CERTIFICATE', storage_path('app/private/wallet/apple-pass.p12')),
        'certificate_password' => env('WALLET_APPLE_CERTIFICATE_PASSWORD', ''),
        'wwdr' => env('WALLET_APPLE_WWDR', storage_path('app/private/wallet/AppleWWDRCAG4.pem')),
    ],

    // Google Wallet: Issuer ID de la Google Pay & Wallet Console y llave JSON
    // de la cuenta de servicio con acceso a la API de Google Wallet.
    'google' => [
        'enabled' => (bool) env('WALLET_GOOGLE_ENABLED', false),
        'issuer_id' => env('WALLET_GOOGLE_ISSUER_ID'),
        'credentials' => env('WALLET_GOOGLE_CREDENTIALS', storage_path('app/private/wallet/google-service-account.json')),
    ],

];
