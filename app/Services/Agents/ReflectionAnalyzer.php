<?php

namespace App\Services\Agents;

use App\Ai\Agents\EmbeddedAgent;
use App\Enums\Agents\ReflectionApplyBehavior;
use App\Enums\Agents\ReflectionRunStatus;
use App\Enums\Agents\ReflectionStatus;
use App\Enums\Agents\ReflectionType;
use App\Enums\RunStatus;
use App\Events\Runs\RunCompleted;
use App\Events\Runs\RunFailed;
use App\Jobs\Agents\ApplyReflectionJob;
use App\Models\Agents\Agent;
use App\Models\Agents\AgentMessage;
use App\Models\Agents\AgentSession;
use App\Models\Agents\Reflection;
use App\Models\Agents\ReflectionRun;
use App\Models\Agents\ReflectionSettings;
use App\Models\Runs\Run;
use App\Notifications\Agents\ReflectionReportNotification;
use App\Services\Billing\CreditGate;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Reviews an `Agent`'s `AgentSession`s since its last reflection, mines them
 * for recurring patterns, and proposes `Reflection`s: new/updated skills,
 * instruction changes, or flagged tool gaps.
 *
 * This collapses the reference feature's multi-step "mine candidates →
 * validate against transcripts → check existing knowledge" pipeline
 * (docs/gumloop/output/raw/core-concepts/reflections.md) into one prompt and
 * one parse: a single `EmbeddedAgent` call already receives the full
 * transcripts, the agent's current instructions, its attached skills, and its
 * still-pending reflections, so it can validate and de-duplicate in the same
 * pass rather than three separate LLM calls. That keeps one reflection pass
 * at roughly the cost of a single moderate conversation.
 *
 * A minimum-confidence, minimum-support auto-apply gate still runs entirely
 * in code afterward (`isAutoApplyEligible()`), so a model that scores its own
 * proposal generously can't unilaterally decide it's safe to apply.
 */
class ReflectionAnalyzer
{
    private const AUTO_APPLY_MIN_CONFIDENCE = 75;

    private const AUTO_APPLY_MIN_SUPPORT = 3;

    public function __construct(
        private readonly CreditGate $creditGate,
        private readonly NotificationDispatcher $notifications,
    ) {}

    public function run(Agent $agent): ReflectionRun
    {
        $settings = $agent->reflectionSettings ?? $agent->reflectionSettings()->save(new ReflectionSettings);

        $this->creditGate->assertCanStartRun($agent->workspace);

        $sessions = $agent->sessions()
            ->with('messages')
            ->when($settings->last_run_at, fn ($query) => $query->where('created_at', '>', $settings->last_run_at))
            ->get();

        $reflectionRun = $agent->reflectionRuns()->create(['workspace_id' => $agent->workspace_id]);

        if ($sessions->count() < $settings->min_chats_threshold) {
            return $this->skip($agent, $settings, $reflectionRun, $sessions->count());
        }

        $reflectionRun->forceFill(['status' => ReflectionRunStatus::Running, 'started_at' => now()])->save();

        $run = $this->openRun($reflectionRun, $agent);

        try {
            $candidates = $this->minePatterns($agent, $settings, $sessions);
            $created = $this->proposeReflections($agent, $reflectionRun, $settings, $sessions, $candidates);

            $reflectionRun->forceFill([
                'status' => ReflectionRunStatus::Completed,
                'sessions_analyzed_count' => $sessions->count(),
                'finished_at' => now(),
            ])->save();

            $run->forceFill([
                'status' => RunStatus::Completed,
                'output' => ['reflections_proposed' => $created],
                'finished_at' => now(),
            ])->save();

            event(new RunCompleted($run));
        } catch (Throwable $e) {
            $reflectionRun->forceFill([
                'status' => ReflectionRunStatus::Failed,
                'skip_reason' => $e->getMessage(),
                'finished_at' => now(),
            ])->save();

            $run->forceFill(['status' => RunStatus::Failed, 'error' => $e->getMessage(), 'finished_at' => now()])->save();

            event(new RunFailed($run));
        }

        $settings->forceFill(['last_run_at' => now()])->save();

        $this->report($agent, $settings, $reflectionRun->fresh());

        return $reflectionRun->fresh();
    }

    private function skip(Agent $agent, ReflectionSettings $settings, ReflectionRun $reflectionRun, int $sessionCount): ReflectionRun
    {
        $reflectionRun->forceFill([
            'status' => ReflectionRunStatus::Skipped,
            'skip_reason' => "Only {$sessionCount} session(s) since the last run; needs at least {$settings->min_chats_threshold}.",
            'sessions_analyzed_count' => $sessionCount,
            'finished_at' => now(),
        ])->save();

        $settings->forceFill(['last_run_at' => now()])->save();

        $this->report($agent, $settings, $reflectionRun);

        return $reflectionRun;
    }

    /**
     * @param  Collection<int, AgentSession>  $sessions
     * @return array<int, array<string, mixed>>
     */
    private function minePatterns(Agent $agent, ReflectionSettings $settings, Collection $sessions): array
    {
        $transcript = $sessions
            ->map(fn (AgentSession $session): string => "Session #{$session->id} ({$session->created_at->toDateString()}):\n".
                $session->messages->map(fn (AgentMessage $message): string => "{$message->role->value}: {$message->content}")->implode("\n"))
            ->implode("\n\n---\n\n");

        $existingSkills = $agent->skills->isEmpty()
            ? 'None.'
            : $agent->skills->map(fn ($skill): string => "- [id {$skill->id}] {$skill->name}: {$skill->instructions}")->implode("\n");

        $pendingTitles = $agent->reflections()->where('status', ReflectionStatus::Pending->value)->pluck('title');
        $pending = $pendingTitles->isEmpty() ? 'None.' : $pendingTitles->implode('; ');

        $extra = $settings->extra_instructions
            ? "\nExtra focus requested by the agent owner:\n{$settings->extra_instructions}\n"
            : '';

        $prompt = <<<PROMPT
            You are reviewing an AI agent's recent conversation transcripts to find recurring patterns worth
            fixing: repeated mistakes, inefficient tool usage, missing knowledge, or requests the agent handled
            the same multi-step way more than once. Only propose a pattern that shows up across at least 2
            distinct sessions and isn't already covered by the agent's current instructions or skills below.

            Agent's current instructions:
            {$agent->instructions}

            Agent's existing skills:
            {$existingSkills}

            Reflections already pending for this agent (do not repeat these):
            {$pending}
            {$extra}
            Transcripts since the last reflection:
            {$transcript}

            Respond with ONLY a JSON array (no prose, no markdown fences). Each element must have exactly
            these keys:
            {
              "type": "new_skill" | "skill_fix" | "instruction_update" | "tool_access",
              "title": short string,
              "rationale": why this change is needed, citing which session numbers show the pattern,
              "confidence": integer 0-100,
              "support_count": integer, how many distinct sessions show this pattern,
              "proposed_prompt": the exact instructions text to apply (skill body or instruction update),
              "target_skill_id": the existing skill id to update — only for "skill_fix", otherwise null
            }

            Return an empty array [] if nothing qualifies.
            PROMPT;

        $response = (new EmbeddedAgent('You analyze agent conversation histories and propose structured improvements.'))
            ->prompt($prompt, provider: $agent->provider, model: $agent->model);

        $decoded = json_decode(trim($response->text), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  Collection<int, AgentSession>  $sessions
     * @param  array<int, array<string, mixed>>  $candidates
     */
    private function proposeReflections(
        Agent $agent,
        ReflectionRun $reflectionRun,
        ReflectionSettings $settings,
        Collection $sessions,
        array $candidates,
    ): int {
        $created = 0;

        foreach ($candidates as $candidate) {
            $type = ReflectionType::tryFrom((string) ($candidate['type'] ?? ''));

            if ($type === null || empty($candidate['title']) || empty($candidate['proposed_prompt'])) {
                continue;
            }

            // A newer proposal for the same recurring pattern supersedes the
            // older pending one rather than sitting alongside it.
            $agent->reflections()
                ->where('status', ReflectionStatus::Pending->value)
                ->where('title', $candidate['title'])
                ->update(['status' => ReflectionStatus::Superseded->value]);

            $reflection = $reflectionRun->reflections()->create([
                'workspace_id' => $agent->workspace_id,
                'agent_id' => $agent->id,
                'type' => $type->value,
                'title' => (string) $candidate['title'],
                'rationale' => (string) ($candidate['rationale'] ?? ''),
                'evidence' => ['session_ids' => $sessions->pluck('id')->all()],
                'confidence' => max(0, min(100, (int) ($candidate['confidence'] ?? 0))),
                'support_count' => max(0, (int) ($candidate['support_count'] ?? 0)),
                'proposed_prompt' => (string) $candidate['proposed_prompt'],
                'target_skill_id' => $type === ReflectionType::SkillFix ? ($candidate['target_skill_id'] ?? null) : null,
            ]);

            $created++;

            if ($this->isAutoApplyEligible($settings, $reflection)) {
                ApplyReflectionJob::dispatch($reflection);
            }
        }

        return $created;
    }

    private function isAutoApplyEligible(ReflectionSettings $settings, Reflection $reflection): bool
    {
        return $settings->apply_behavior === ReflectionApplyBehavior::AutoApply
            && $reflection->type->isAutoApplyEligible()
            && $reflection->confidence >= self::AUTO_APPLY_MIN_CONFIDENCE
            && $reflection->support_count >= self::AUTO_APPLY_MIN_SUPPORT;
    }

    private function openRun(ReflectionRun $reflectionRun, Agent $agent): Run
    {
        $run = $reflectionRun->runs()->create([
            'workspace_id' => $agent->workspace_id,
            'trigger_type' => 'reflection',
            'input' => ['reflection_run_id' => $reflectionRun->id],
        ]);

        $run->forceFill(['status' => RunStatus::Running, 'started_at' => now()])->save();

        return $run;
    }

    private function report(Agent $agent, ReflectionSettings $settings, ReflectionRun $reflectionRun): void
    {
        if ($reflectionRun->status === ReflectionRunStatus::Skipped && ! $settings->notify_on_skip) {
            return;
        }

        // Which channel(s) each recipient actually receives this on (email,
        // in-app, webhook) is decided by `WorkspaceEventNotification::via()`
        // against their own `NotificationPreference` for
        // `ReflectionRunCompleted` — this only decides whether the event
        // fires at all.
        $this->notifications->dispatch(
            $this->notifications->ownersAndAdmins($agent->workspace),
            new ReflectionReportNotification($reflectionRun),
        );
    }
}
