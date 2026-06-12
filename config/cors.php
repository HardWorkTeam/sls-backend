<?php

// Local dev origins are always allowed. Production origins come from the
// CORS_ALLOWED_ORIGINS env var (comma-separated list of full URLs), so the
// deployed admin/client/RSVP apps can reach the API without code changes.
$localOrigins = [
    'http://localhost:3000',
    'http://localhost:3001',
    'http://localhost:3002',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:3001',
    'http://127.0.0.1:3002',
];

$envOrigins = array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique([...$localOrigins, ...$envOrigins])),

    // Optional regex patterns, e.g. allow all Vercel preview deploys:
    // CORS_ALLOWED_ORIGIN_PATTERNS="#^https://.*\.vercel\.app$#"
    'allowed_origins_patterns' => array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', '')),
    )),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
