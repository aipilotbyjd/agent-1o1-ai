<?php

use App\Models\Notifications\NotificationChannel;
use App\Models\User;
use App\Services\Http\SsrfGuard;
use App\Services\Workspaces\WorkspaceService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = app(WorkspaceService::class)->create($this->owner, ['name' => 'Acme']);

    app()->instance(SsrfGuard::class, new SsrfGuard(fn (string $host) => $host === 'hooks.example.com' ? ['93.184.216.34'] : []));

    Passport::actingAs($this->owner);
});

it('rejects a notification channel URL that points at an internal address', function (string $url) {
    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels", [
        'type' => 'webhook',
        'name' => 'Internal',
        'config' => ['url' => $url],
    ])->assertUnprocessable()->assertJsonValidationErrors('config.url');

    expect(NotificationChannel::query()->count())->toBe(0);
})->with([
    'loopback' => 'http://127.0.0.1:6379/',
    'metadata' => 'http://169.254.169.254/latest/meta-data/',
    'private network' => 'http://10.0.0.5/hook',
    'localhost' => 'http://localhost/hook',
    'credentials' => 'https://user:pass@hooks.example.com/hook',
]);

it('rejects pointing an existing channel at an internal address', function () {
    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'config' => ['url' => 'https://hooks.example.com/hook'],
    ]);

    $this->patchJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels/{$channel->id}", [
        'config' => ['url' => 'http://192.168.0.1/admin'],
    ])->assertUnprocessable()->assertJsonValidationErrors('config.url');

    expect($channel->fresh()->config['url'])->toBe('https://hooks.example.com/hook');
});

it('accepts a public notification channel URL', function () {
    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels", [
        'type' => 'webhook',
        'name' => 'Public',
        'config' => ['url' => 'https://hooks.example.com/hook'],
    ])->assertCreated();
});

it('delivers a test notification to a public webhook', function () {
    Http::fake(['https://hooks.example.com/*' => Http::response('ok')]);

    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'config' => ['url' => 'https://hooks.example.com/hook', 'headers' => ['X-Token' => 'abc']],
    ]);

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels/{$channel->id}/test")
        ->assertOk();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://hooks.example.com/hook'
        && $request->header('X-Token') === ['abc']);
});

it('refuses to deliver to an internal address stored before validation existed', function () {
    Http::fake();

    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'config' => ['url' => 'http://169.254.169.254/latest/meta-data/'],
    ]);

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels/{$channel->id}/test")
        ->assertStatus(400)
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'blocked'));

    Http::assertNothingSent();
});

it('refuses to deliver when the host resolves to a private address', function () {
    Http::fake();

    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'config' => ['url' => 'https://rebind.example.com/hook'],
    ]);

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels/{$channel->id}/test")
        ->assertStatus(400);

    Http::assertNothingSent();
});

it('reports a failed delivery by status code', function () {
    Http::fake(['https://hooks.example.com/*' => Http::response('nope', 500)]);

    $channel = NotificationChannel::factory()->create([
        'workspace_id' => $this->workspace->id,
        'config' => ['url' => 'https://hooks.example.com/hook'],
    ]);

    $this->postJson("/api/v1/workspaces/{$this->workspace->id}/notification-channels/{$channel->id}/test")
        ->assertStatus(400)
        ->assertJsonPath('message', 'Delivery failed: HTTP 500.');
});
