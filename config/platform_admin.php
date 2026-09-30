<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin API
    |--------------------------------------------------------------------------
    |
    | The `/v1/admin/*` HTTP API (driven by the Bruno collection's `Admin`
    | folder). Off, every admin route answers 404 as if it didn't exist, and
    | admins work purely from the `referrals:*` / `admin:*` artisan commands.
    */

    'api_enabled' => (bool) env('PLATFORM_ADMIN_API_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Two-factor requirement
    |--------------------------------------------------------------------------
    |
    | Platform admins can hand out credits and plan time to any workspace, so
    | the `/v1/admin/*` routes refuse an admin account that hasn't confirmed
    | two-factor authentication. Only switch this off locally.
    */

    'require_two_factor' => (bool) env('PLATFORM_ADMIN_REQUIRE_2FA', true),

];
