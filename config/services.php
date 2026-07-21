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

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
    ],

    // Telegram bot used to alert the admin team when a couple submits a manual
    // package payment. Both values must be set for notifications to fire;
    // otherwise the notifier is a silent no-op.
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'admin_chat_id' => env('TELEGRAM_ADMIN_CHAT_ID'),
        // Optional forum-topic (message thread) id inside the admin group. When
        // set, messages post into that topic instead of the group's General.
        'admin_topic_id' => env('TELEGRAM_ADMIN_TOPIC_ID'),
        // Admin dashboard base URL, used to deep-link the payments screen.
        'admin_url' => env('ADMIN_APP_URL', 'http://localhost:3000'),
    ],

    'rsvp' => [
        // Public RSVP website base URL, used for invitation links + QR codes.
        'url' => env('RSVP_APP_URL', 'http://localhost:3002'),
        // Shared secret for the RSVP site's on-demand cache-revalidation
        // webhook. When set, publishing/editing an invitation tells Next.js to
        // drop its cached copy immediately (otherwise it expires on its own TTL).
        'revalidate_secret' => env('RSVP_REVALIDATE_SECRET'),
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
