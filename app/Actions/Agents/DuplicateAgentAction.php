<?php

namespace App\Actions\Agents;

use App\Models\Agents\Agent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copies an agent's *configuration* — instructions, model settings, attached
 * tools, skills, workflows and always-injected knowledge. Deliberately not
 * copied: sessions (a conversation belongs to the agent that had it),
 * memories (facts learned about one agent's users are not facts about
 * another's), version history (the copy starts at version 1, written by
 * `AgentObserver`), and artifacts.
 *
 * Mirrors `WorkflowController::duplicate()`'s shape so both objects behave
 * the same way in the UI.
 */
class DuplicateAgentAction
{
    public function execute(Agent $agent, ?User $creator = null, ?string $name = null): Agent
    {
        return DB::transaction(function () use ($agent, $creator, $name): Agent {
            $copy = $agent->workspace->agents()->create([
                'name' => $name ?? "{$agent->name} (copy)",
                'slug' => Str::slug($name ?? $agent->name).'-'.Str::random(6),
                'description' => $agent->description,
                'instructions' => $agent->instructions,
                'provider' => $agent->provider,
                'model' => $agent->model,
                'temperature' => $agent->temperature,
                'settings' => $agent->settings,
                'created_by' => $creator?->id,
            ]);

            foreach ($agent->toolBindings as $binding) {
                $copy->toolBindings()->create([
                    'node_type' => $binding->node_type,
                    'config' => $binding->config,
                    'exposed_fields' => $binding->exposed_fields,
                ]);
            }

            $copy->skills()->sync($agent->skills->pluck('id'));
            $copy->workflows()->sync($agent->workflows->pluck('id'));

            foreach ($agent->knowledge as $entry) {
                $copy->knowledge()->create($entry->only([
                    'title', 'content', 'source_type', 'source_url', 'file_path',
                    'tokens', 'is_active', 'sort_order', 'metadata',
                ]));
            }

            return $copy;
        });
    }
}
