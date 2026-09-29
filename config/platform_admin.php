<?php

return [

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
