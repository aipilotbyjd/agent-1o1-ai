<?php

use App\Ai\Agents\WorkflowBuilderAgent;
use App\Ai\Tools\WorkflowBuilder\ConnectNodesTool;
use App\Ai\Tools\WorkflowBuilder\DisconnectNodesTool;
use App\Ai\Tools\WorkflowBuilder\ReadDraftTool;
use App\Enums\Workflows\BuilderSessionStatus;
use App\Exceptions\WorkflowBuilderConflictException;
use App\Models\User;
use App\Models\Workflows\Builder\WorkflowBuilderSession;
use App\Services\Workspaces\WorkspaceService;
use Laravel\Ai\Tools\Request as ToolRequest;

function builderDraftSession(array $graph = ['nodes' => [], 'edges' => []]): WorkflowBuilderSession
{
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);

    return WorkflowBuilderSession::factory()->forWorkspace($workspace, $owner)->create(['draft_graph' => $graph]);
}

function transformNode(string $key): array
{
    return ['key' => $key, 'type' => 'transform', 'config' => ['mapping' => []], 'position' => null];
}

it('labels every snapshot with the edit that made it', function () {
    $session = builderDraftSession();

    $session->addNode('fetch', 'transform', ['mapping' => []]);
    $session->addNode('send', 'transform', ['mapping' => []]);
    $session->connect('fetch', 'send');
    $session->disconnect('fetch', 'send');
    $session->removeNode('send');

    expect($session->draftVersions()->oldest()->oldest('id')->pluck('label')->all())->toBe([
        'Added node fetch',
        'Added node send',
        'Connected fetch → send',
        'Disconnected fetch → send',
        'Removed node send',
    ]);
    expect($session->fresh()->draft_lock_version)->toBe(5);
});

it('refuses an edit from a copy of the session that is behind the draft', function () {
    $session = builderDraftSession();
    $stale = WorkflowBuilderSession::find($session->id);

    $session->addNode('fetch', 'transform', ['mapping' => []]);

    expect(fn () => $stale->addNode('other', 'transform', ['mapping' => []]))
        ->toThrow(WorkflowBuilderConflictException::class);

    expect($session->fresh()->currentGraph()['nodes'])->toHaveCount(1);
    expect($session->draftVersions()->count())->toBe(1);
});

it('replaces the draft from the canvas when the lock version is current', function () {
    $session = builderDraftSession();

    $session->replaceDraft([
        'nodes' => [transformNode('a'), transformNode('b')],
        'edges' => [['from' => 'a', 'to' => 'b']],
    ], 0);

    $session->refresh();
    expect($session->currentGraph()['nodes'])->toHaveCount(2);
    expect($session->currentGraph()['edges'][0])->toBe(['from' => 'a', 'to' => 'b', 'condition' => null]);
    expect($session->draftVersions()->value('label'))->toBe('Synced from canvas');
});

it('refuses a canvas sync from behind the draft', function () {
    $session = builderDraftSession();
    $session->addNode('fetch', 'transform', ['mapping' => []]);

    expect(fn () => $session->replaceDraft(['nodes' => [], 'edges' => []], 0))
        ->toThrow(WorkflowBuilderConflictException::class);

    expect($session->fresh()->currentGraph()['nodes'])->toHaveCount(1);
});

it('rejects a canvas sync with an invalid graph', function (array $graph, string $message) {
    $session = builderDraftSession();

    expect(fn () => $session->replaceDraft($graph, 0))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'duplicate keys' => [['nodes' => [transformNode('a'), transformNode('a')], 'edges' => []], "Duplicate node key 'a'"],
    'unknown type' => [['nodes' => [['key' => 'a', 'type' => 'nope', 'config' => []]], 'edges' => []], 'no node for type'],
    'dangling edge' => [['nodes' => [transformNode('a')], 'edges' => [['from' => 'a', 'to' => 'ghost']]], "unknown target node 'ghost'"],
]);

it('restores an earlier snapshot as a new, undoable snapshot', function () {
    $session = builderDraftSession();
    $session->addNode('fetch', 'transform', ['mapping' => []]);
    $firstVersion = $session->draftVersions()->first();
    $session->addNode('send', 'transform', ['mapping' => []]);

    $session->restoreVersion($firstVersion);

    expect(array_column($session->fresh()->currentGraph()['nodes'], 'key'))->toBe(['fetch']);
    expect($session->draftVersions()->count())->toBe(3);
    expect($session->draftVersions()->latest()->latest('id')->value('label'))->toBe('Restored Added node fetch');
});

it('refuses to edit an archived session', function () {
    $session = builderDraftSession();
    $session->update(['status' => BuilderSessionStatus::Archived]);

    expect(fn () => $session->assertEditable())->toThrow(WorkflowBuilderConflictException::class);
});

it('reads the current draft, including edits made since the tool was built', function () {
    $session = builderDraftSession(['nodes' => [transformNode('a')], 'edges' => []]);
    $tool = new ReadDraftTool($session);

    WorkflowBuilderSession::find($session->id)->addNode('b', 'transform', ['mapping' => []]);

    $draft = json_decode((string) $tool->handle(new ToolRequest([], 'call-1')), true);

    expect(array_column($draft['nodes'], 'key'))->toBe(['a', 'b']);
    expect($draft['entry_nodes'])->toBe(['a', 'b']);
});

it('tells the model to re-read the draft when the user changed it mid-turn', function () {
    $session = builderDraftSession(['nodes' => [transformNode('a'), transformNode('b')], 'edges' => []]);
    $tools = collect(iterator_to_array((new WorkflowBuilderAgent($session))->tools()))->keyBy(fn ($tool) => $tool->name());

    // The canvas saves while the agent is mid-turn.
    WorkflowBuilderSession::find($session->id)->removeNode('b');

    $result = (string) $tools['connect_nodes']->handle(new ToolRequest(['from' => 'a', 'to' => 'b'], 'call-1'));

    expect($tools['connect_nodes'])->toBeInstanceOf(ConnectNodesTool::class);
    expect($result)->toContain('read_draft');
    // The session was reloaded, so the retry works from the user's draft.
    expect(array_column($session->currentGraph()['nodes'], 'key'))->toBe(['a']);
});

it('reports a missing edge instead of recording an empty edit', function () {
    $session = builderDraftSession(['nodes' => [transformNode('a'), transformNode('b')], 'edges' => []]);

    $result = (string) (new DisconnectNodesTool($session))->handle(new ToolRequest(['from' => 'a', 'to' => 'b'], 'call-1'));

    expect($result)->toContain('There is no edge');
    expect($session->draftVersions()->count())->toBe(0);
});

it('stores a repeated edge from the canvas once', function () {
    $session = builderDraftSession();

    $session->replaceDraft([
        'nodes' => [transformNode('a'), transformNode('b')],
        'edges' => [['from' => 'a', 'to' => 'b'], ['from' => 'a', 'to' => 'b']],
    ], 0);

    expect($session->fresh()->currentGraph()['edges'])->toBe([['from' => 'a', 'to' => 'b', 'condition' => null]]);
});

it('disconnects only the edge with a given condition, turning an always-edge into an error path', function () {
    $session = builderDraftSession([
        'nodes' => [transformNode('a'), transformNode('b')],
        'edges' => [['from' => 'a', 'to' => 'b', 'condition' => null]],
    ]);
    $session->connect('a', 'b', 'error');

    $result = (string) (new DisconnectNodesTool($session))->handle(new ToolRequest(['from' => 'a', 'to' => 'b', 'condition' => 'none'], 'call-1'));

    expect($result)->toBe('Disconnected [a] from [b].');
    expect($session->fresh()->currentGraph()['edges'])->toBe([['from' => 'a', 'to' => 'b', 'condition' => 'error']]);

    $missing = (string) (new DisconnectNodesTool($session))->handle(new ToolRequest(['from' => 'a', 'to' => 'b', 'condition' => 'none'], 'call-2'));
    expect($missing)->toContain('with that condition');
});
