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

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
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

    'rsvp' => [
        // Public RSVP website base URL, used for invitation links + QR codes.
        'url' => env('RSVP_APP_URL', 'http://localhost:3002'),
    ],

    'client' => [
        // Couple portal base URL, used for password-reset links.
        'url' => env('CLIENT_APP_URL', 'http://localhost:3001'),
    ],

    // Platform's own payment details, shown to couples when paying for a
    // package (manual KHQR / bank transfer — no payment gateway).
    'platform_payment' => [
        'bank_name' => env('PLATFORM_BANK_NAME', 'ABA Bank'),
        'account_name' => env('PLATFORM_ACCOUNT_NAME', 'Srolanh Management'),
        'account_number' => env('PLATFORM_ACCOUNT_NUMBER', '000 000 000'),
        'khqr_image_url' => env('PLATFORM_KHQR_IMAGE_URL'), // a static KHQR QR image
        'instructions' => env('PLATFORM_PAYMENT_INSTRUCTIONS', 'Scan the KHQR or transfer to the account above, then enter your transaction reference.'),
    ],

];
