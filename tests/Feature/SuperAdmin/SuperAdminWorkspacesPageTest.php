<?php

use App\Models\Billing\UsagePeriod;
use App\Models\Runs\Run;
use App\Models\Workspaces\Workspace;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

it('lists workspaces and searches by owner email', function () {
    referralUser(['email' => 'owner-one@acme.test', 'name' => 'Owner One']);
    referralUser(['email' => 'owner-two@other.test', 'name' => 'Owner Two']);
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.workspaces.index'))
        ->assertOk()
        ->assertSee("Owner One's Workspace")
        ->assertSee("Owner Two's Workspace");

    $this->get(route('super-admin.workspaces.index', ['q' => 'acme.test']))
        ->assertSee("Owner One's Workspace")
        ->assertDontSee("Owner Two's Workspace");
});

it('hides deleted workspaces unless asked', function () {
    $workspace = referralUser(['name' => 'Gone'])->ownedWorkspaces()->first();
    $workspace->delete();
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.workspaces.index'))->assertDontSee("Gone's Workspace");
    $this->get(route('super-admin.workspaces.index', ['deleted' => 'only']))->assertSee("Gone's Workspace");
    $this->get(route('super-admin.workspaces.show', $workspace))->assertOk()->assertSee('Deleted');
});

it('shows a workspace with its owner, members and recent runs', function () {
    $owner = referralUser(['email' => 'owner@acme.test', 'name' => 'Dana']);
    $workspace = $owner->ownedWorkspaces()->first();
    Run::factory()->forWorkspace($workspace)->create();
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.workspaces.show', $workspace))
        ->assertOk()
        ->assertSee("Dana's Workspace")
        ->assertSee('owner@acme.test')
        ->assertSee('Members (1)')
        ->assertSee('All runs for this workspace');
});

it('does not write anything when a workspace is viewed', function () {
    $workspace = referralUser()->ownedWorkspaces()->first();
    UsagePeriod::query()->delete();
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.workspaces.show', $workspace))->assertOk()->assertSee('No period yet');

    expect(UsagePeriod::query()->count())->toBe(0);
});

it('answers 404 for an unknown workspace', function () {
    signInAsSuperAdmin(superAdmin());

    $this->get(route('super-admin.workspaces.show', fake()->uuid()))->assertNotFound();
    expect(Workspace::query()->count())->toBe(0);
});
