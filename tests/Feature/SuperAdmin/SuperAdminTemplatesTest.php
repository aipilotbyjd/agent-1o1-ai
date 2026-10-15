<?php

use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\Templates\AgentTemplate;
use App\Models\Templates\TemplateCollection;
use App\Models\Templates\WorkflowTemplate;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();

    $this->superAdmin = superAdmin();
    signInAsSuperAdmin($this->superAdmin);
});

it('lists gallery templates and workspace templates separately', function () {
    WorkflowTemplate::factory()->global()->create(['name' => 'Gallery Lead Router']);
    WorkflowTemplate::factory()->create(['name' => 'Customer Private Flow']);

    $this->get(route('super-admin.templates.index'))
        ->assertOk()
        ->assertSee('Gallery Lead Router')
        ->assertDontSee('Customer Private Flow');

    $this->get(route('super-admin.templates.index', ['scope' => 'workspaces']))
        ->assertOk()
        ->assertSee('Customer Private Flow')
        ->assertDontSee('Gallery Lead Router')
        ->assertSee('Copy to gallery');

    AgentTemplate::factory()->global()->create(['name' => 'Support Agent']);

    $this->get(route('super-admin.templates.index', ['kind' => 'agent']))->assertSee('Support Agent');
});

it('edits a gallery template and logs the change', function () {
    $template = WorkflowTemplate::factory()->global()->create(['name' => 'Old name']);

    $this->put(route('super-admin.templates.update', ['workflow', $template->id]), [
        'name' => 'New name',
        'description' => 'Routes inbound leads',
        'category' => 'sales',
    ])->assertRedirect(route('super-admin.templates.index', ['kind' => 'workflow']));

    expect($template->fresh()->name)->toBe('New name')
        ->and($template->fresh()->category)->toBe('sales');

    $entry = SuperAdminAuditLog::query()->where('action', 'template.updated')->sole();
    expect($entry->before)->toMatchArray(['name' => 'Old name'])
        ->and($entry->after)->toMatchArray(['name' => 'New name']);
});

it('never edits or publishes a customer template in place', function () {
    $customer = WorkflowTemplate::factory()->create(['name' => 'Customer Private Flow']);

    $this->get(route('super-admin.templates.edit', ['workflow', $customer->id]))->assertNotFound();
    $this->put(route('super-admin.templates.update', ['workflow', $customer->id]), ['name' => 'Hijacked'])->assertNotFound();
    $this->post(route('super-admin.templates.publish', ['workflow', $customer->id]), ['published' => 1])->assertNotFound();
    $this->delete(route('super-admin.templates.destroy', ['workflow', $customer->id]))->assertNotFound();

    expect($customer->fresh()->name)->toBe('Customer Private Flow')
        ->and($customer->fresh()->visibility)->toBe('private');
});

it('copies a workspace template into the gallery as a hidden draft', function () {
    $customer = WorkflowTemplate::factory()->create(['name' => 'Great Flow', 'slug' => 'great-flow', 'graph' => ['nodes' => [['id' => 'a', 'type' => 'http_request']], 'edges' => []]]);
    WorkflowTemplate::factory()->global()->create(['slug' => 'great-flow']);

    $this->post(route('super-admin.templates.copy-to-gallery', ['workflow', $customer->id]))->assertRedirect();

    $copy = WorkflowTemplate::query()->whereNull('workspace_id')->where('name', 'Great Flow')->sole();

    expect($copy->visibility)->toBe('private')
        ->and($copy->slug)->toBe('great-flow-2')
        ->and($copy->created_by)->toBeNull()
        ->and($copy->usage_count)->toBe(0)
        ->and($copy->graph)->toBe($customer->graph)
        ->and($customer->fresh()->workspace_id)->not->toBeNull()
        ->and(SuperAdminAuditLog::query()->where('action', 'template.copied_to_gallery')->exists())->toBeTrue();
});

it('publishes and hides a gallery template', function () {
    $template = AgentTemplate::factory()->global()->create(['visibility' => 'private']);

    $this->post(route('super-admin.templates.publish', ['agent', $template->id]), ['published' => 1])->assertRedirect();
    expect($template->fresh()->visibility)->toBe('public');

    $this->post(route('super-admin.templates.publish', ['agent', $template->id]), ['published' => 0])->assertRedirect();
    expect($template->fresh()->visibility)->toBe('private')
        ->and(SuperAdminAuditLog::query()->whereIn('action', ['template.published', 'template.unpublished'])->count())->toBe(2);
});

it('builds a gallery collection from gallery templates only', function () {
    $gallery = WorkflowTemplate::factory()->global()->create(['name' => 'Gallery Flow']);
    $agent = AgentTemplate::factory()->global()->create(['name' => 'Gallery Agent']);
    $customer = WorkflowTemplate::factory()->create(['name' => 'Customer Flow']);

    $this->post(route('super-admin.templates.collections.store'), ['name' => 'Sales starter pack'])->assertRedirect();
    $collection = TemplateCollection::query()->whereNull('workspace_id')->sole();

    expect($collection->visibility)->toBe('private')
        ->and($collection->slug)->toBe('sales-starter-pack');

    $this->post(route('super-admin.templates.collections.items.store', $collection), ['template' => "workflow:{$gallery->id}"])->assertRedirect();
    $this->post(route('super-admin.templates.collections.items.store', $collection), ['template' => "agent:{$agent->id}"])->assertRedirect();
    $this->post(route('super-admin.templates.collections.items.store', $collection), ['template' => "workflow:{$gallery->id}"])->assertRedirect();
    $this->post(route('super-admin.templates.collections.items.store', $collection), ['template' => "workflow:{$customer->id}"])->assertNotFound();

    expect($collection->items()->count())->toBe(2);

    $this->get(route('super-admin.templates.collections.show', $collection))
        ->assertOk()
        ->assertSee('Gallery Flow')
        ->assertSee('Gallery Agent')
        ->assertDontSee('Customer Flow');

    $second = $collection->items()->get()->last();
    $this->post(route('super-admin.templates.collections.items.move', [$collection, $second]), ['direction' => 'up'])->assertRedirect();
    expect($collection->items()->first()->templatable_id)->toBe($agent->id);

    $this->delete(route('super-admin.templates.collections.items.destroy', [$collection, $second]))->assertRedirect();
    expect($collection->items()->count())->toBe(1);
});

it('removes a deleted template from every collection', function () {
    $template = WorkflowTemplate::factory()->global()->create();
    $collection = TemplateCollection::factory()->global()->create();
    $collection->items()->create(['templatable_type' => 'workflow_template', 'templatable_id' => $template->id, 'position' => 0]);

    $this->delete(route('super-admin.templates.destroy', ['workflow', $template->id]))->assertRedirect();

    expect(WorkflowTemplate::query()->find($template->id))->toBeNull()
        ->and($collection->items()->count())->toBe(0);
});

it('never opens a workspace collection', function () {
    $collection = TemplateCollection::factory()->create();

    $this->get(route('super-admin.templates.collections.show', $collection))->assertNotFound();
    $this->post(route('super-admin.templates.collections.publish', $collection), ['published' => 1])->assertNotFound();
});

it('renders the template and collection pages', function () {
    $template = WorkflowTemplate::factory()->global()->create();
    $collection = TemplateCollection::factory()->global()->create();

    foreach ([
        route('super-admin.templates.edit', ['workflow', $template->id]),
        route('super-admin.templates.collections.index'),
        route('super-admin.templates.collections.create'),
        route('super-admin.templates.collections.edit', $collection),
    ] as $url) {
        $this->get($url)->assertOk();
    }
});
