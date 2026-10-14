<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Location
    |--------------------------------------------------------------------------
    |
    | The admin panel answers only on `domain` (e.g. `admin.example.com`; null
    | matches any host) and under `path`. Every other host or path returns a
    | plain 404, so the panel is not discoverable from the public app.
    */

    'domain' => env('ADMIN_PANEL_DOMAIN'),

    'path' => env('ADMIN_PANEL_PATH', 'jd'),

    /*
    |--------------------------------------------------------------------------
    | IP allowlist
    |--------------------------------------------------------------------------
    |
    | Comma-separated addresses or CIDR ranges. Empty allows any address.
    | Requests from elsewhere get a 404. Behind a proxy, make sure trusted
    | proxies are configured or every request will appear to come from it.
    */

    'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('ADMIN_PANEL_ALLOWED_IPS', ''))))),

    /*
    |--------------------------------------------------------------------------
    | Session
    |--------------------------------------------------------------------------
    |
    | The panel uses its own cookie (host-only, Secure, SameSite=Strict,
    | encrypted) so it never shares a session with the main app. An admin is
    | signed out after `idle_timeout` minutes without a request and after
    | `absolute_lifetime` minutes regardless of activity.
    */

    'session_cookie' => env('ADMIN_PANEL_SESSION_COOKIE', 'admin_session'),

    'idle_timeout' => (int) env('ADMIN_PANEL_IDLE_TIMEOUT', 30),

    'absolute_lifetime' => (int) env('ADMIN_PANEL_ABSOLUTE_LIFETIME', 480),

    /*
    |--------------------------------------------------------------------------
    | Sign-in
    |--------------------------------------------------------------------------
    |
    | After a correct password the browser has `challenge_timeout` minutes to
    | answer the two-factor step, and `max_challenge_attempts` wrong codes
    | before it is sent back to the password form.
    */

    'challenge_timeout' => (int) env('ADMIN_PANEL_CHALLENGE_TIMEOUT', 5),

    'max_challenge_attempts' => (int) env('ADMIN_PANEL_MAX_CHALLENGE_ATTEMPTS', 5),

];
