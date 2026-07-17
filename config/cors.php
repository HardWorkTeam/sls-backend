<?php

// Local dev origins are allowed ONLY outside production. Production origins come
// from the CORS_ALLOWED_ORIGINS env var (comma-separated list of full URLs), so
// the deployed admin/client/RSVP apps can reach the API without code changes.
// In production we never fall back to localhost and never use a wildcard origin.
$isProduction = env('APP_ENV') === 'production';

$localOrigins = $isProduction ? [] : [
    'http://localhost:3000',
    'http://localhost:3001',
    'http://localhost:3002',
    'http://localhost:3003',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:3001',
    'http://127.0.0.1:3002',
    'http://127.0.0.1:3003',
];

$envOrigins = array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Explicit method list — no wildcard.
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Explicit origin allow-list (env-driven in production). Never '*'.
    'allowed_origins' => array_values(array_unique([...$localOrigins, ...$envOrigins])),

    // Optional regex patterns, e.g. allow all Vercel preview deploys:
    // CORS_ALLOWED_ORIGIN_PATTERNS="#^https://.*\.vercel\.app$#"
    'allowed_origins_patterns' => array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', '')),
    )),

    // Explicit header list — covers what the SPA clients send (Sanctum token,
    // JSON, XSRF). No wildcard.
    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
