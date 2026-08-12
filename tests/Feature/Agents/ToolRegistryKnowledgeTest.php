<?php

use App\Ai\Tools\RememberTool;
use App\Ai\Tools\SearchKnowledgeTool;
use App\Models\Agents\Agent;
use App\Models\Agents\DocumentEmbedding;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Agents\ToolRegistry;
use App\Services\Workspaces\WorkspaceService;

it('auto-attaches SearchKnowledgeTool once the workspace has any embedded chunks', function () {
    $owner = User::factory()->create();
    $workspace = app(WorkspaceService::class)->create($owner, ['name' => 'Acme']);
    $agent = Agent::factory()->forWorkspace($workspace)->create();
    $run = Run::factory()->create();

    $tools = app(ToolRegistry::class)->toolsFor($agent, $run);
    expect($tools)->toHaveCount(1);
    expect($tools[0])->toBeInstanceOf(RememberTool::class);

    DocumentEmbedding::create(['workspace_id' => $workspace->id, 'source' => 's', 'chunk_text' => 'x', 'embedding' => [1.0]]);

    $tools = app(ToolRegistry::class)->toolsFor($agent, $run);

    expect($tools)->toHaveCount(2);
    expect($tools[0])->toBeInstanceOf(SearchKnowledgeTool::class);
    expect($tools[1])->toBeInstanceOf(RememberTool::class);
});
