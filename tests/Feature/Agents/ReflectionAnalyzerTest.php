<?php

use App\Ai\Agents\EmbeddedAgent;
use App\Enums\Agents\ReflectionRunStatus;
use App\Enums\Agents\ReflectionStatus;
use App\Enums\Notifications\NotificationEvent;
use App\Jobs\Agents\ApplyReflectionJob;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Agents\ReflectionSettings;
use App\Models\Runs\Run;
use App\Models\User;
use App\Notifications\Agents\ReflectionReportNotification;
use App\Services\Agents\ReflectionAnalyzer;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

/**
 * @return array{0: Agent, 1: User}
 */
function reflectionAgent(int $sessionCount = 3): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create(['instructions' => 'You are a helpful assistant.']);

    for ($i = 0; $i < $sessionCount; $i++) {
        $session = AgentSession::factory()->forAgent($agent)->create();
        AgentMessage::factory()->forSession($session)->create(['content' => 'How do I get a refund?']);
        AgentMessage::factory()->forSession($session)->assistant()->create(['content' => 'I am not sure, let me check.']);
    }

    return [$agent->fresh(), $owner];
}

it('skips the run when there are fewer sessions than the minimum threshold', function () {
    [$agent] = reflectionAgent(sessionCount: 2);
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 5]);

    $run = app(ReflectionAnalyzer::class)->run($agent);

    expect($run->status)->toBe(ReflectionRunStatus::Skipped);
    expect($run->sessions_analyzed_count)->toBe(2);
    expect($run->reflections)->toHaveCount(0);
});

it('proposes reflections parsed from the mining response', function () {
    [$agent] = reflectionAgent(sessionCount: 3);
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 2]);

    EmbeddedAgent::fake([json_encode([
        [
            'type' => 'instruction_update',
            'title' => 'Clarify refund policy',
            'rationale' => 'The agent hedges on refund questions across sessions 1-3.',
            'confidence' => 80,
            'support_count' => 3,
            'proposed_prompt' => 'Refunds are available within 30 days of purchase.',
            'target_skill_id' => null,
        ],
    ])]);

    $run = app(ReflectionAnalyzer::class)->run($agent);

    expect($run->status)->toBe(ReflectionRunStatus::Completed);
    expect($run->sessions_analyzed_count)->toBe(3);

    $reflection = $run->reflections()->sole();
    expect($reflection->title)->toBe('Clarify refund policy');
    expect($reflection->status)->toBe(ReflectionStatus::Pending);
    expect($reflection->confidence)->toBe(80);

    $analysisRun = Run::where('runnable_type', 'reflection_run')->where('runnable_id', $run->id)->sole();
    expect($analysisRun->trigger_type)->toBe('reflection');
});

it('ignores candidates missing required fields', function () {
    [$agent] = reflectionAgent(sessionCount: 3);
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 2]);

    EmbeddedAgent::fake([json_encode([
        ['type' => 'bogus_type', 'title' => 'x', 'proposed_prompt' => 'y'],
        ['type' => 'new_skill', 'title' => '', 'proposed_prompt' => 'y'],
    ])]);

    $run = app(ReflectionAnalyzer::class)->run($agent);

    expect($run->reflections)->toHaveCount(0);
});

it('treats an unparseable mining response as no candidates instead of failing', function () {
    [$agent] = reflectionAgent(sessionCount: 3);
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 2]);

    EmbeddedAgent::fake(['not valid json']);

    $run = app(ReflectionAnalyzer::class)->run($agent);

    expect($run->status)->toBe(ReflectionRunStatus::Completed);
    expect($run->reflections)->toHaveCount(0);
});

it('auto-applies eligible reflections only when auto-apply is enabled', function () {
    Bus::fake();

    [$agent] = reflectionAgent(sessionCount: 3);
    ReflectionSettings::factory()->forAgent($agent)->autoApply()->create(['min_chats_threshold' => 2]);

    EmbeddedAgent::fake([json_encode([
        [
            'type' => 'instruction_update',
            'title' => 'Clarify refund policy',
            'rationale' => 'Recurs across sessions.',
            'confidence' => 90,
            'support_count' => 5,
            'proposed_prompt' => 'Refunds are available within 30 days.',
        ],
    ])]);

    app(ReflectionAnalyzer::class)->run($agent);

    Bus::assertDispatched(ApplyReflectionJob::class);
});

it('does not auto-apply a low-confidence reflection even with auto-apply enabled', function () {
    Bus::fake();

    [$agent] = reflectionAgent(sessionCount: 3);
    ReflectionSettings::factory()->forAgent($agent)->autoApply()->create(['min_chats_threshold' => 2]);

    EmbeddedAgent::fake([json_encode([
        [
            'type' => 'instruction_update',
            'title' => 'Weak signal',
            'rationale' => 'Only barely recurring.',
            'confidence' => 40,
            'support_count' => 1,
            'proposed_prompt' => 'Some change.',
        ],
    ])]);

    app(ReflectionAnalyzer::class)->run($agent);

    Bus::assertNotDispatched(ApplyReflectionJob::class);
});

it('does not auto-apply a tool_access reflection regardless of confidence', function () {
    Bus::fake();

    [$agent] = reflectionAgent(sessionCount: 3);
    ReflectionSettings::factory()->forAgent($agent)->autoApply()->create(['min_chats_threshold' => 2]);

    EmbeddedAgent::fake([json_encode([
        [
            'type' => 'tool_access',
            'title' => 'Needs Salesforce access',
            'rationale' => 'The agent keeps asking for data it cannot fetch.',
            'confidence' => 99,
            'support_count' => 10,
            'proposed_prompt' => 'Grant the Salesforce connector.',
        ],
    ])]);

    app(ReflectionAnalyzer::class)->run($agent);

    Bus::assertNotDispatched(ApplyReflectionJob::class);
});

it('supersedes a still-pending reflection with the same title', function () {
    [$agent] = reflectionAgent(sessionCount: 3);
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 1]);

    EmbeddedAgent::fake([json_encode([
        ['type' => 'instruction_update', 'title' => 'Same pattern', 'rationale' => 'r', 'confidence' => 60, 'support_count' => 2, 'proposed_prompt' => 'v1'],
    ])]);
    $firstRun = app(ReflectionAnalyzer::class)->run($agent);
    $first = $firstRun->reflections()->sole();

    $this->travel(1)->minutes();
    AgentSession::factory()->forAgent($agent)->create();

    EmbeddedAgent::fake([json_encode([
        ['type' => 'instruction_update', 'title' => 'Same pattern', 'rationale' => 'r2', 'confidence' => 70, 'support_count' => 3, 'proposed_prompt' => 'v2'],
    ])]);
    app(ReflectionAnalyzer::class)->run($agent);

    expect($first->fresh()->status)->toBe(ReflectionStatus::Superseded);
    expect($agent->reflections()->where('status', ReflectionStatus::Pending->value)->sole()->proposed_prompt)->toBe('v2');
});

it('notifies workspace owners and admins when reflections are proposed', function () {
    Notification::fake();

    [$agent, $owner] = reflectionAgent(sessionCount: 3);
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 2]);

    EmbeddedAgent::fake([json_encode([
        ['type' => 'instruction_update', 'title' => 'x', 'rationale' => 'r', 'confidence' => 60, 'support_count' => 2, 'proposed_prompt' => 'p'],
    ])]);

    app(ReflectionAnalyzer::class)->run($agent);

    Notification::assertSentTo($owner, ReflectionReportNotification::class, fn ($notification) => $notification->event === NotificationEvent::ReflectionRunCompleted);
});

it('only notifies about a skipped run when notify_on_skip is enabled', function () {
    Notification::fake();

    [$agent] = reflectionAgent(sessionCount: 1);
    ReflectionSettings::factory()->forAgent($agent)->create(['min_chats_threshold' => 5]);

    app(ReflectionAnalyzer::class)->run($agent);

    Notification::assertNothingSent();
});
