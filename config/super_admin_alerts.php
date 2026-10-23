<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kill switch
    |--------------------------------------------------------------------------
    |
    | Silences every Super Admin alert without touching a call site. Kept
    | apart from `config/admin_alerts.php`, which covers billing and usage.
    |
    */

    'enabled' => (bool) env('SUPER_ADMIN_ALERTS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Recipients
    |--------------------------------------------------------------------------
    |
    | Fixed destinations read from the environment, never Super Admin models,
    | so alerting still works while the database is the problem.
    |
    */

    'recipients' => [
        'mail' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SUPER_ADMIN_ALERT_EMAILS', '')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Which events raise an alert.
    |
    */

    'events' => [
        'login_new_ip' => true,
        'locked_out' => true,
        'refresh_reuse_detected' => true,
        'ip_blocked' => true,
        'two_factor_reset' => true,
        'account_created' => true,
        'account_disabled' => true,
        'role_changed' => true,
    ],

];
