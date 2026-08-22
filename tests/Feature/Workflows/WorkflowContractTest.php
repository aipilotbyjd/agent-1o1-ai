<?php

use App\Models\Auth\ApiKey;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workspaces\Workspace;
use App\Services\Workflows\ContractGenerator;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Passport\Passport;

function contractWorkspace(): array
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return [$owner, $workspace];
}

function contractWorkflow(Workspace $workspace, User $owner, bool $publish = true): Workflow
{
    $workflow = Workflow::factory()->forWorkspace($workspace)->create();
    $workflow->replaceGraph([
        'nodes' => [
            ['key' => 'fetch', 'type' => 'call_api', 'config' => [
                'method' => 'GET',
                'url' => 'https://api.example.test/{{ input.customer_id }}',
            ]],
            ['key' => 'shape', 'type' => 'transform', 'config' => [
                'mapping' => ['email' => 'nodes.fetch.body.email', 'name' => 'nodes.fetch.body.name'],
            ]],
        ],
        'edges' => [['from' => 'fetch', 'to' => 'shape', 'condition' => null]],
    ]);

    if ($publish) {
        $workflow->publishVersion(publisher: $owner);
    }

    return $workflow->fresh();
}

it('derives an output schema from each node\'s declared output shape', function () {
    [$owner, $workspace] = contractWorkspace();

    $contract = app(ContractGenerator::class)->generate(contractWorkflow($workspace, $owner));

    // `runs.output` is keyed by node key (GraphAdvancer), so the contract is
    // too.
    expect(array_keys($contract['output']['properties']))->toBe(['fetch', 'shape']);

    expect($contract['output']['properties']['fetch']['properties'])
        ->toHaveKeys(['status', 'headers', 'body']);

    // TransformNode's shape comes from its own config — the mapping keys.
    expect(array_keys($contract['output']['properties']['shape']['properties']))
        ->toBe(['email', 'name']);
});

it('derives the input schema from the template references in the graph', function () {
    [$owner, $workspace] = contractWorkspace();

    $contract = app(ContractGenerator::class)->generate(contractWorkflow($workspace, $owner));

    expect($contract['input']['type'])->toBe('object');
    expect($contract['input']['properties'])->toHaveKey('customer_id');
    // Derived fields are never required — nothing declared them.
    expect($contract['input'])->not->toHaveKey('required');
});

it('uses a declared interface for the input schema, including required and enum', function () {
    [$owner, $workspace] = contractWorkspace();

    $workflow = contractWorkflow($workspace, $owner);
    $workflow->update(['input_schema' => ['fields' => [
        ['key' => 'customer_id', 'label' => 'Customer', 'type' => 'string', 'required' => true, 'help' => 'The customer ref'],
        ['key' => 'tier', 'label' => 'Tier', 'type' => 'select', 'required' => false, 'default' => 'free', 'options' => [
            ['value' => 'free', 'label' => 'Free'],
            ['value' => 'pro', 'label' => 'Pro'],
        ]],
    ]]]);

    $contract = app(ContractGenerator::class)->generate($workflow->fresh());

    expect($contract['input']['required'])->toBe(['customer_id']);
    expect($contract['input']['properties']['customer_id']['description'])->toBe('The customer ref');
    expect($contract['input']['properties']['tier']['type'])->toBe('string');
    expect($contract['input']['properties']['tier']['enum'])->toBe(['free', 'pro']);
    expect($contract['input']['properties']['tier']['default'])->toBe('free');
});

it('reports the published version, falling back to the draft when unpublished', function () {
    [$owner, $workspace] = contractWorkspace();

    $published = app(ContractGenerator::class)->generate(contractWorkflow($workspace, $owner));
    expect($published['source'])->toBe('published');
    expect($published['version'])->toBe(1);

    $draft = app(ContractGenerator::class)->generate(contractWorkflow($workspace, $owner, publish: false));
    expect($draft['source'])->toBe('draft');
    expect($draft['version'])->toBeNull();
});

it('serves the contract over the internal api', function () {
    [$owner, $workspace] = contractWorkspace();
    $workflow = contractWorkflow($workspace, $owner);

    Passport::actingAs($owner);

    $this->getJson("/api/v1/workspaces/{$workspace->id}/workflows/{$workflow->id}/contract")
        ->assertOk()
        ->assertJsonPath('data.contract.workflow_id', $workflow->id)
        ->assertJsonPath('data.contract.source', 'published')
        ->assertJsonStructure(['data' => ['contract' => ['input', 'output', 'version']]]);
});

it('serves the contract over the public api under workflows:read', function () {
    [$owner, $workspace] = contractWorkspace();
    $workflow = contractWorkflow($workspace, $owner);

    $plainTextKey = ApiKey::generatePlainTextKey();
    $workspace->apiKeys()->create([
        'name' => 'Integration',
        'hashed_key' => ApiKey::hash($plainTextKey),
        'abilities' => ['workflows:read'],
    ]);

    $this->withToken($plainTextKey)
        ->getJson("/api/public/v1/workflows/{$workflow->id}/contract")
        ->assertOk()
        ->assertJsonPath('data.contract.workflow_id', $workflow->id);
});
