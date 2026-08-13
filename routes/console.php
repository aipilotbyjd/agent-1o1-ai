<?php

use App\Jobs\System\ExpireStaleWaitsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// onOneServer() requires a shared cache store (Redis or database) in production.
Schedule::command('triggers:run-due')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('triggers:retry-stuck')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::job(new ExpireStaleWaitsJob)->everyMinute()->onOneServer();
Schedule::command('billing:expire-trials')->hourly()->withoutOverlapping()->onOneServer();
