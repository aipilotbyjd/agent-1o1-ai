<?php

use App\Models\SuperAdmin\SuperAdminAuditLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

function failedJob(string $queue = 'system-notification', string $exception = "RuntimeException: SMTP timeout\n#0 /app/Mailer.php(10)"): string
{
    $uuid = (string) Str::uuid();

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => $queue,
        'payload' => json_encode([
            'uuid' => $uuid,
            'displayName' => 'App\\Jobs\\SendWeeklyDigest',
            'data' => ['command' => 'O:8:"stdClass":1:{s:6:"secret";s:21:"customer-secret-value";}'],
        ]),
        'exception' => $exception,
        'failed_at' => now(),
    ]);

    return $uuid;
}

it('lists failed jobs without their payloads', function () {
    $uuid = failedJob();
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.system.failed-jobs.index'))
        ->assertOk()
        ->assertSee('SendWeeklyDigest')
        ->assertSee('RuntimeException: SMTP timeout')
        ->assertDontSee('customer-secret-value');

    $this->get(route('super-admin.system.failed-jobs.show', $uuid))
        ->assertOk()
        ->assertSee('Mailer.php')
        ->assertDontSee('customer-secret-value');
});

it('filters failed jobs by queue', function () {
    failedJob('system-notification', 'MailException: one');
    failedJob('ai-agent', 'AgentException: two');
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.system.failed-jobs.index', ['queue' => 'ai-agent']))
        ->assertSee('AgentException: two')
        ->assertDontSee('MailException: one');
});

it('retries a failed job behind the password and logs it', function () {
    $uuid = failedJob();
    $admin = superAdmin();
    signInAsSuperAdmin($admin);

    Artisan::spy();

    $this->post(route('super-admin.system.failed-jobs.retry', $uuid), ['current_password' => 'wrong'])
        ->assertSessionHasErrorsIn('support', 'current_password');

    Artisan::shouldNotHaveReceived('call');

    $this->post(route('super-admin.system.failed-jobs.retry', $uuid), ['current_password' => 'password'])
        ->assertRedirect(route('super-admin.system.failed-jobs.index'));

    Artisan::shouldHaveReceived('call')->with('queue:retry', ['id' => [$uuid]])->once();

    expect(SuperAdminAuditLog::query()->where('action', 'failed_job.retried')->where('super_admin_id', $admin->id)->exists())->toBeTrue();
});

it('deletes a failed job behind the password and logs it', function () {
    $uuid = failedJob();
    signInAsSuperAdmin(superAdmin());

    $this->delete(route('super-admin.system.failed-jobs.destroy', $uuid), ['current_password' => 'password'])
        ->assertRedirect(route('super-admin.system.failed-jobs.index'));

    expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse()
        ->and(SuperAdminAuditLog::query()->where('action', 'failed_job.deleted')->exists())->toBeTrue();
});

it('answers 404 for an unknown failed job', function () {
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.system.failed-jobs.show', (string) Str::uuid()))->assertNotFound();
    $this->post(route('super-admin.system.failed-jobs.retry', (string) Str::uuid()), ['current_password' => 'password'])->assertNotFound();
});

it('shows every application queue with its failed count', function () {
    failedJob('ai-agent');
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.system.queues.index'))
        ->assertOk()
        ->assertSee('workflows-execute')
        ->assertSee('system-notification')
        ->assertSee(route('super-admin.system.failed-jobs.index', ['queue' => 'ai-agent']), false);
});

it('wires confirmation prompts through a nonce script instead of inline handlers', function () {
    signInAsSuperAdmin(superAdmin());

    $response = $this->get(route('super-admin.system.queues.index'))->assertOk();

    preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'), $matches);

    expect($matches[1] ?? null)->not->toBeNull();
    $response->assertSee('<script nonce="'.$matches[1].'">', false)
        ->assertDontSee('onsubmit=', false);
});
