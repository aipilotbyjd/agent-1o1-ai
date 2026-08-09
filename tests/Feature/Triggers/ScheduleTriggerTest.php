<?php

use App\Models\Triggers\Trigger;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

function makeScheduleTrigger(string $cron): Trigger
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return Trigger::factory()->schedule($cron)->forWorkspace($workspace)->create();
}

it('queues an event for a due schedule trigger', function () {
    $trigger = makeScheduleTrigger('* * * * *');

    $this->artisan('triggers:run-due');

    expect($trigger->events()->count())->toBe(1);
});

it('queues nothing for a non-due schedule', function () {
    $farFuture = now()->addYear();
    $trigger = makeScheduleTrigger("{$farFuture->minute} {$farFuture->hour} 1 1 *");

    $this->artisan('triggers:run-due');

    expect($trigger->events()->count())->toBe(0);
});

it('produces exactly one row when the due command runs twice in the same minute', function () {
    $trigger = makeScheduleTrigger('* * * * *');

    $this->artisan('triggers:run-due');
    $this->artisan('triggers:run-due');

    expect($trigger->events()->count())->toBe(1);
});
