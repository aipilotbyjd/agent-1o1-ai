<?php

use App\Enums\Triggers\TriggerEventStatus;
use App\Enums\Triggers\TriggerType;
use App\Jobs\Triggers\FireTriggerEvent;
use App\Models\Triggers\Trigger;
use App\Models\Triggers\TriggerEvent;
use App\Models\User;
use App\Services\Triggers\TriggerService;
use App\Services\Workspaces\WorkspaceService;

function makeQueuedEvent(array $triggerOverrides = []): TriggerEvent
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $trigger = Trigger::factory()->webhook()->forWorkspace($workspace)->create($triggerOverrides);

    return $trigger->events()->create([
        'source' => TriggerType::Webhook,
        'status' => TriggerEventStatus::Queued,
        'payload' => ['a' => 1],
        'delivery_id' => 'd-1',
    ]);
}

it('fires a workflow run and marks the event fired', function () {
    $event = makeQueuedEvent();

    (new FireTriggerEvent($event->trigger, $event))->handle(app(TriggerService::class));

    $event->refresh();

    expect($event->status)->toBe(TriggerEventStatus::Fired);
    expect($event->workflow_run_id)->not->toBeNull();
    expect($event->attempts)->toBe(1);
});

it('claim is exclusive — a second claim on the same event returns false', function () {
    $event = makeQueuedEvent();

    expect($event->claim())->toBeTrue();
    expect($event->fresh()->claim())->toBeFalse();
});

it('does not re-fire a terminal event on re-dispatch', function () {
    $event = makeQueuedEvent();
    $event->markFired(999);

    (new FireTriggerEvent($event->trigger, $event))->handle(app(TriggerService::class));

    expect($event->fresh()->workflow_run_id)->toBe(999);
});

it('records a circuit-breaker failure only when the event actually reached the target', function () {
    $event = makeQueuedEvent();
    $event->update(['attempts' => 1]);

    $job = new FireTriggerEvent($event->trigger, $event);
    $job->failed(new RuntimeException('boom'));

    $event->refresh();

    expect($event->status)->toBe(TriggerEventStatus::Failed);
    expect($event->trigger->fresh()->consecutive_failure_count)->toBe(1);
});

it('does not count a failure for an event that expired unclaimed', function () {
    $event = makeQueuedEvent();
    // attempts stays 0 — the job never actually claimed/ran.

    $job = new FireTriggerEvent($event->trigger, $event);
    $job->failed(new RuntimeException('lock contention'));

    expect($event->trigger->fresh()->consecutive_failure_count)->toBe(0);
});
