<?php

use App\Jobs\System\ExpireStaleWaitsJob;
use App\Jobs\System\FailStuckAgentTurnsJob;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled Commands
|--------------------------------------------------------------------------
|
| Every command runs on one server and never overlaps itself. onOneServer()
| requires a shared cache store (Redis or database) in production.
|
*/

Schedule::onOneServer()->withoutOverlapping()->group(function (): void {
    // Workflow triggers
    Schedule::command('triggers:run-due')->everyMinute();
    Schedule::command('triggers:retry-stuck')->everyFiveMinutes();

    // Agents
    Schedule::command('agents:expire-actions')->everyMinute();
    Schedule::command('reflections:run-due')->everyFifteenMinutes();

    // Assistant
    Schedule::command('assistant:expire-actions')->everyMinute();
    Schedule::command('assistant:run-due-briefings')->everyMinute();
    Schedule::command('assistant:run-due-triggers')->everyMinute();
    Schedule::command('assistant:check-inboxes')->everyTwoMinutes();
    Schedule::command('assistant:sync-meetings')->everyTenMinutes();

    // Connectors, knowledge & skills
    Schedule::command('connectors:refresh-expiring')->everyFiveMinutes();
    Schedule::command('connectors:check-health')->hourly();
    Schedule::command('knowledge:sync-sources')->everyFifteenMinutes();
    Schedule::command('skills:sync-sources')->everyFifteenMinutes();

    // Workflow builder
    Schedule::command('workflow-builder:archive-idle')->dailyAt('03:00');

    // Billing
    Schedule::command('billing:expire-trials')->hourly();
    Schedule::command('billing:expire-plan-grants')->hourly();
    Schedule::command('billing:notify-trial-ending')->daily();
    Schedule::command('billing:invoice-overage')->daily();

    // Referrals
    Schedule::command('referrals:grant-pending')->hourly();
    Schedule::command('referrals:notify-plan-time-ending')->daily();
    Schedule::command('referrals:admin-digest')->dailyAt('08:00');
    Schedule::command('referrals:prune-visits')->weekly();
});

/*
|--------------------------------------------------------------------------
| Scheduled Jobs
|--------------------------------------------------------------------------
|
| Queued maintenance jobs. They only dispatch, so overlap locks aren't needed.
|
*/

Schedule::job(new ExpireStaleWaitsJob)->everyMinute()->onOneServer();
Schedule::job(new FailStuckAgentTurnsJob)->everyFiveMinutes()->onOneServer();
