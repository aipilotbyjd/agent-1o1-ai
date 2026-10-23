<?php

use App\Enums\SuperAdmin\SuperAdminRole;
use App\Models\Connectors\Connector;
use App\Models\Nodes\CustomNode;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use App\Models\Templates\AgentTemplate;
use App\Models\Templates\WorkflowTemplate;
use App\Models\User;
use App\Services\Workspaces\WorkspaceService;

beforeEach(function () {
    $this->admin = superAdmin(SuperAdminRole::Operator);
    $this->headers = superAdminHeaders($this->admin);
});

it('switches a connector off and on, recording why', function () {
    $connector = Connector::factory()->create(['key' => 'gmail', 'name' => 'Gmail', 'is_active' => true]);

    $this->withHeaders($this->headers)->getJson(saUrl('catalog/connectors'))->assertOk()->assertJsonPath('data.0.key', 'gmail');

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/connectors/{$connector->id}/disable"), ['reason' => 'Provider outage, case 77'])
        ->assertOk()->assertJsonPath('data.is_active', false);

    $this->withHeaders($this->headers)->getJson(saUrl('catalog/connectors?active=0'))->assertJsonCount(1, 'data');

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/connectors/{$connector->id}/enable"), ['reason' => 'Provider is back'])
        ->assertOk()->assertJsonPath('data.is_active', true);

    $log = SuperAdminAuditLog::query()->where('action', 'catalog.connector.disabled')->firstOrFail();

    expect($log->before)->toBe(['is_active' => true])->and($log->reason)->toBe('Provider outage, case 77');
});

it('switches a custom node off and on', function () {
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $node = CustomNode::factory()->create(['workspace_id' => $workspace->id, 'is_active' => true]);

    $this->withHeaders($this->headers)->getJson(saUrl('catalog/nodes'))->assertOk()->assertJsonPath('data.0.workspace.name', 'Acme');

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/nodes/{$node->id}/disable"), ['reason' => 'Calls a blocked host'])
        ->assertOk()->assertJsonPath('data.is_active', false);

    expect($node->fresh()->is_active)->toBeFalse();
});

it('publishes and unpublishes platform templates but never a workspace\'s own', function () {
    $platform = WorkflowTemplate::factory()->create(['workspace_id' => null, 'visibility' => 'private']);
    $workspace = app(WorkspaceService::class)->create(User::factory()->create(), ['name' => 'Acme']);
    $private = WorkflowTemplate::factory()->create(['workspace_id' => $workspace->id, 'visibility' => 'private']);

    $this->withHeaders($this->headers)->getJson(saUrl('catalog/templates/workflow?platform=1'))->assertOk()->assertJsonCount(1, 'data');

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/templates/workflow/{$platform->id}/publish"), ['reason' => 'Reviewed and approved'])
        ->assertOk()->assertJsonPath('data.visibility', 'public');

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/templates/workflow/{$private->id}/publish"), ['reason' => 'Reviewed and approved'])
        ->assertStatus(409);

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/templates/workflow/{$platform->id}/unpublish"), ['reason' => 'Contains a broken step'])
        ->assertOk()->assertJsonPath('data.visibility', 'private');

    expect(SuperAdminAuditLog::query()->whereIn('action', ['catalog.template.published', 'catalog.template.unpublished'])->count())->toBe(2);
});

it('handles each template type and refuses an unknown one', function () {
    $agent = AgentTemplate::factory()->create(['workspace_id' => null, 'visibility' => 'private']);

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/templates/agent/{$agent->id}/publish"), ['reason' => 'Reviewed and approved'])->assertOk();
    $this->withHeaders($this->headers)->getJson(saUrl('catalog/templates/spreadsheet'))->assertNotFound();
});

it('needs a reason and the catalog ability', function () {
    $connector = Connector::factory()->create();

    $this->withHeaders($this->headers)->postJson(saUrl("catalog/connectors/{$connector->id}/disable"))
        ->assertUnprocessable()->assertJsonValidationErrors('reason');

    foreach ([SuperAdminRole::Support, SuperAdminRole::Finance, SuperAdminRole::Viewer] as $role) {
        $this->withHeaders(superAdminHeaders(superAdmin($role)))->getJson(saUrl('catalog/connectors'))->assertForbidden();
    }
});
