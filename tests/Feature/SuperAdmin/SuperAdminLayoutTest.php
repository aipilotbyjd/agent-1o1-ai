<?php

use App\Models\Referrals\ReferralProgram;
use App\Models\Templates\WorkflowTemplate;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
    signInAsSuperAdmin(superAdmin());
});

it('shows the failed job count in the sidebar', function () {
    $this->get(route('super-admin.dashboard'))
        ->assertOk()
        ->assertDontSee('title="1 failed job"', false);

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\Example']),
        'exception' => 'RuntimeException: boom',
        'failed_at' => now(),
    ]);

    $this->get(route('super-admin.dashboard'))
        ->assertOk()
        ->assertSee('title="1 failed job"', false);
});

it('keeps the templates link active while editing a template', function () {
    $template = WorkflowTemplate::factory()->global()->create();

    $this->get(route('super-admin.templates.edit', ['workflow', $template->id]))
        ->assertOk()
        ->assertSee('href="'.route('super-admin.templates.index').'" class="group relative flex items-center gap-3 rounded-lg px-3 py-1.5 text-sm font-medium transition focus-visible:outline-2 focus-visible:outline-indigo-400 bg-white/10', false);
});

it('links to account settings from the signed in admin card', function () {
    $this->get(route('super-admin.account'))
        ->assertOk()
        ->assertSee('href="'.route('super-admin.account').'"', false)
        ->assertSeeInOrder(['title="Account settings"', 'aria-current="page"'], false);
});

it('summarises pagination and offers to clear filters on empty results', function () {
    $user = User::factory()->create();

    $this->get(route('super-admin.users.index'))
        ->assertOk()
        ->assertSeeInOrder(['Showing', '1–1', 'of', '1'], false);

    $this->get(route('super-admin.users.index', ['q' => 'no-such-user-anywhere']))
        ->assertOk()
        ->assertSee('No users match.')
        ->assertSee('Clear filters')
        ->assertDontSee('Showing');

    $this->get(route('super-admin.users.show', $user))
        ->assertOk()
        ->assertSee('data-copy="'.$user->id.'"', false);
});

it('renders page links with gaps for long result sets', function () {
    $paginator = new LengthAwarePaginator(range(1, 50), 1000, 50, 10, ['path' => 'https://example.test/users']);

    $this->blade('<x-super-admin.pagination :paginator="$paginator" />', ['paginator' => $paginator])
        ->assertSeeInOrder(['451–500', '1,000'])
        ->assertSee('aria-current="page">10</span>', false)
        ->assertSee('href="https://example.test/users?page=1"', false)
        ->assertSee('href="https://example.test/users?page=20"', false)
        ->assertSee('rel="next"', false)
        ->assertSee('…');
});

it('highlights active filters and links to remove each one', function () {
    $this->get(route('super-admin.runs.index'))
        ->assertOk()
        ->assertDontSee('sa-filter-active', false)
        ->assertDontSee('Clear all');

    $this->get(route('super-admin.runs.index', ['status' => 'failed', 'from' => '2026-01-01']))
        ->assertOk()
        ->assertSee('<option value="failed" selected>Failed</option>', false)
        ->assertSee('title="Remove status filter"', false)
        ->assertSee('href="'.e(route('super-admin.runs.index', ['from' => '2026-01-01'])).'"', false)
        ->assertSee('href="'.e(route('super-admin.runs.index', ['status' => 'failed'])).'"', false)
        ->assertSee('Clear all');
});

it('shows validation errors next to the field that caused them', function () {
    $this->from(route('super-admin.account'))
        ->put(route('super-admin.account.password'), [
            'current_password' => 'not-my-password',
            'password' => 'a-brand-new-password-123',
            'password_confirmation' => 'a-brand-new-password-123',
        ])
        ->assertRedirect(route('super-admin.account'));

    $this->get(route('super-admin.account'))
        ->assertOk()
        ->assertSee('sa-field sa-field-invalid', false)
        ->assertSee('id="current_password-error"', false);
});

it('only shows reward fields for the chosen reward type', function () {
    $program = ReferralProgram::factory()->create();

    $this->get(route('super-admin.referrals.programs.rules.create', $program))
        ->assertOk()
        ->assertSee('data-show-when="reward_type:credits"', false)
        ->assertSee('data-show-when="reward_type:plan_time"', false)
        ->assertSee('data-show-when="trigger:milestone"', false)
        ->assertSee('Bonus credits');
});
