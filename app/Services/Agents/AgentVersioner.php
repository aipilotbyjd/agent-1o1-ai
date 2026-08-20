<?php

namespace App\Services\Agents;

use App\Models\Agents\Agent;
use App\Models\Agents\AgentToolBinding;
use App\Models\Agents\AgentVersion;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Writes and replays `agent_versions` — the Agent layer's counterpart to
 * `Workflow::publishVersion()`, minus the publish step: an agent has no
 * draft/published split, so every behavioral edit simply becomes the next
 * version.
 *
 * What a snapshot captures is deliberately wider than what
 * `AgentSession::pinnedAgent()` replays. The scalar behavior fields are
 * pinned to a live conversation; attached tools and skills are recorded for
 * audit and restore only, and are always read live at run time. Replaying an
 * old binding set would resurrect tools an admin has since detached —
 * possibly because the credential behind one was revoked — which is not a
 * decision a months-old snapshot should get to make.
 */
class AgentVersioner
{
    /**
     * Attributes whose change means "the agent behaves differently now" and
     * therefore warrants a new version. Cosmetic edits (name, description)
     * are captured *in* a snapshot but don't create one on their own.
     *
     * @var array<int, string>
     */
    public const array BEHAVIORAL_ATTRIBUTES = ['instructions', 'provider', 'model', 'temperature', 'settings'];

    public function snapshot(Agent $agent, ?User $changedBy = null): AgentVersion
    {
        return DB::transaction(function () use ($agent, $changedBy): AgentVersion {
            $next = ((int) $agent->versions()->lockForUpdate()->max('version')) + 1;

            try {
                return $agent->versions()->create([
                    'version' => $next,
                    'snapshot' => $this->snapshotPayload($agent),
                    'changed_by' => $changedBy?->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Two concurrent edits raced for the same version number.
                // The loser retries against the now-higher watermark rather
                // than failing the edit that triggered it.
                return $agent->versions()->create([
                    'version' => ((int) $agent->versions()->max('version')) + 1,
                    'snapshot' => $this->snapshotPayload($agent),
                    'changed_by' => $changedBy?->id,
                ]);
            }
        });
    }

    /**
     * Applies an old snapshot's behavior to the live agent. Restoring is an
     * edit like any other, so it produces a *new* version rather than
     * rewinding the history — nothing is ever lost, and the version list
     * reads as a true audit trail.
     */
    public function restore(Agent $agent, AgentVersion $version): AgentVersion
    {
        $snapshot = $version->snapshot;

        $agent->forceFill([
            'instructions' => $snapshot['instructions'] ?? $agent->instructions,
            'provider' => $snapshot['provider'] ?? $agent->provider,
            'model' => $snapshot['model'] ?? null,
            'temperature' => $snapshot['temperature'] ?? null,
            'settings' => $snapshot['settings'] ?? null,
        ])->save();

        // The save above is what writes the new version, via `AgentObserver`
        // — snapshotting again here would mint two versions for one edit.
        // Restoring to the state the agent is already in changes nothing and
        // correctly produces no new version.
        return $this->currentVersion($agent->fresh());
    }

    /**
     * The version a new conversation pins to — the latest, created on the
     * spot for an agent that predates versioning.
     */
    public function currentVersion(Agent $agent): AgentVersion
    {
        return $agent->versions()->latest('version')->first() ?? $this->snapshot($agent);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotPayload(Agent $agent): array
    {
        return [
            'name' => $agent->name,
            'description' => $agent->description,
            'instructions' => $agent->instructions,
            'provider' => $agent->provider,
            'model' => $agent->model,
            'temperature' => $agent->temperature,
            'settings' => $agent->settings,
            'tool_bindings' => $agent->toolBindings()->get()
                ->map(fn (AgentToolBinding $binding): array => [
                    'node_type' => $binding->node_type,
                    'config' => $binding->config,
                    'exposed_fields' => $binding->exposed_fields,
                ])
                ->all(),
            'skill_ids' => $agent->skills()->pluck('skills.id')->all(),
            'workflow_ids' => $agent->workflows()->pluck('workflows.id')->all(),
        ];
    }
}
