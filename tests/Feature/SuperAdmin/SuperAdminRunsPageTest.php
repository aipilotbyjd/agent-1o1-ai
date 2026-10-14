<?php

use App\Enums\RunStatus;
use App\Models\Runs\NodeRun;
use App\Models\Runs\Run;
use App\Services\Workflows\NodeTester;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

it('lists top-level runs and leaves out node tests unless asked', function () {
    $run = Run::factory()->create();
    $test = Run::factory()->forWorkspace($run->workspace)->create(['trigger_type' => NodeTester::TRIGGER_TYPE]);
    $child = Run::factory()->forWorkspace($run->workspace)->create(['parent_run_id' => $run->id]);
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.runs.index'))
        ->assertOk()
        ->assertSee(route('super-admin.runs.show', $run))
        ->assertDontSee(route('super-admin.runs.show', $test))
        ->assertDontSee(route('super-admin.runs.show', $child));

    $this->get(route('super-admin.runs.index', ['include_tests' => 1]))->assertSee(route('super-admin.runs.show', $test));
});

it('filters runs by status and workspace', function () {
    $failed = Run::factory()->create(['status' => RunStatus::Failed]);
    $completed = Run::factory()->forWorkspace($failed->workspace)->create(['status' => RunStatus::Completed]);
    $elsewhere = Run::factory()->create(['status' => RunStatus::Failed]);
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.runs.index', ['status' => 'failed', 'workspace' => $failed->workspace_id]))
        ->assertOk()
        ->assertSee(route('super-admin.runs.show', $failed))
        ->assertDontSee(route('super-admin.runs.show', $completed))
        ->assertDontSee(route('super-admin.runs.show', $elsewhere));
});

it('rejects an unknown status filter', function () {
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.runs.index', ['status' => 'exploded']))->assertSessionHasErrors('status');
});

it('shows a failed run with its steps and errors but not its data', function () {
    $run = Run::factory()->create([
        'status' => RunStatus::Failed,
        'input' => ['customer_secret' => 'do-not-show-this-input'],
    ]);
    $run->forceFill(['error' => 'Step "send_email" failed', 'output' => ['token' => 'do-not-show-this-output']])->save();

    $nodeRun = NodeRun::factory()->create(['run_id' => $run->id, 'key' => 'send_email', 'input' => ['api_key' => 'do-not-show-node-input']]);
    $nodeRun->forceFill(['error' => 'SMTP timeout', 'output' => ['body' => 'do-not-show-node-output']])->save();

    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.runs.show', $run))
        ->assertOk()
        ->assertSee('Step &quot;send_email&quot; failed', false)
        ->assertSee('send_email')
        ->assertSee('SMTP timeout')
        ->assertDontSee('do-not-show-this-input')
        ->assertDontSee('do-not-show-this-output')
        ->assertDontSee('do-not-show-node-input')
        ->assertDontSee('do-not-show-node-output');
});

it('answers 404 for an unknown run', function () {
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.runs.show', fake()->uuid()))->assertNotFound();
});
