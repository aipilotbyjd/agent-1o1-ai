<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Network allowlist
    |--------------------------------------------------------------------------
    |
    | Comma-separated IPs or CIDR ranges. Empty means no global restriction.
    | A non-empty list is enforced before authentication, so everyone else
    | (including the login endpoint) is refused. Each account can narrow this
    | further with its own `allowed_ips`.
    |
    */

    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SUPER_ADMIN_ALLOWED_IPS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | CORS
    |--------------------------------------------------------------------------
    |
    | Origins of the Super Admin frontend. `config/cors.php` reads the same
    | variable so the browser is allowed to call the API from these origins.
    |
    */

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SUPER_ADMIN_ALLOWED_ORIGINS', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Rate limits (requests per minute)
    |--------------------------------------------------------------------------
    |
    | "auth" is keyed by IP and by the email being signed in as, "api" by the
    | authenticated super admin, "sensitive" by the super admin on the
    | endpoints that need a reason and a fresh two-factor code.
    |
    */

    'rate_limits' => [
        'auth' => 5,
        'api' => 120,
        'sensitive' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Step-up confirmation
    |--------------------------------------------------------------------------
    |
    | Sensitive writes need a written reason and a fresh authenticator code.
    |
    */

    'step_up' => [
        'reason_min_length' => 10,
    ],

];
