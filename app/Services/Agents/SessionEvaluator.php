<?php

namespace App\Services\Agents;

use App\Ai\Agents\SessionEvalJudgeAgent;
use App\Enums\Agents\SessionEvaluationGrade;
use App\Enums\Agents\SessionEvaluationStatus;
use App\Enums\RunStatus;
use App\Events\Runs\RunCompleted;
use App\Events\Runs\RunFailed;
use App\Models\Agents\Agent as AgentModel;
use App\Models\Agents\AgentEvaluationSettings;
use App\Models\Agents\AgentSession;
use App\Models\Agents\AgentSessionEvaluation;
use App\Models\Runs\Run;
use App\Notifications\Agents\SessionEvaluationNotifyNotification;
use App\Services\Ai\ModelCatalogResolver;
use App\Services\Billing\CreditGate;
use App\Services\Notifications\NotificationDispatcher;
use RuntimeException;
use Throwable;

/**
 * Grades one `AgentSession`'s transcript against its agent's
 * `AgentEvaluationSettings` and records the result — the live-conversation
 * counterpart to `EvalRunner`'s offline suite grading. See
 * `Listeners\Agents\ScheduleSessionEvaluation` for how this gets called
 * (debounced, off `RunCompleted`) and
 * docs/gumloop/output/raw/core-concepts/evaluations.md for the feature this
 * mirrors.
 *
 * A session that continues after being graded and completes again produces
 * a new evaluation that replaces the old one (`updateOrCreate` on
 * `agent_session_id`), same as Gumloop's "you always see the most recent
 * evaluation result".
 */
class SessionEvaluator
{
    public function __construct(
        private readonly CreditGate $creditGate,
        private readonly SessionEvaluationGrader $grader,
        private readonly NotificationDispatcher $notifications,
        private readonly ModelCatalogResolver $modelCatalog,
    ) {}

    /**
     * Returns null without doing anything when evaluations aren't enabled
     * for this agent — the caller (the queued job) doesn't need to know why
     * an evaluation didn't happen, only that it may not have.
     */
    public function evaluate(AgentSession $session): ?AgentSessionEvaluation
    {
        $agent = $session->agent;
        $settings = $agent->evaluationSettings;

        if ($settings === null || ! $settings->is_enabled) {
            return null;
        }

        $this->creditGate->assertCanStartRun($session->workspace);

        $evaluation = AgentSessionEvaluation::updateOrCreate(
            ['agent_session_id' => $session->id],
            ['workspace_id' => $session->workspace_id, 'agent_id' => $agent->id],
        );

        $evaluation->forceFill(['status' => SessionEvaluationStatus::Evaluating, 'error' => null])->save();

        $run = $this->openRun($evaluation, $session);

        try {
            $decoded = $this->judge($session, $settings);

            $criteriaResults = is_array($decoded['criteria_results'] ?? null) ? $decoded['criteria_results'] : [];
            $callSuccessful = (string) ($decoded['call_successful'] ?? 'unknown');
            $sentiment = $settings->sentiment_enabled ? ($decoded['sentiment'] ?? null) : null;

            $grade = $this->grader->grade(
                $criteriaResults,
                $settings->criteria ?? [],
                $callSuccessful,
                $sentiment,
                $settings->sentiment_affects_grade,
            );

            $evaluation->forceFill([
                'status' => SessionEvaluationStatus::Completed,
                'grade' => $grade,
                'call_successful' => $callSuccessful,
                'sentiment' => $sentiment,
                'summary' => $decoded['summary'] ?? null,
                'criteria_results' => $criteriaResults,
                'data_results' => is_array($decoded['data_results'] ?? null) ? $decoded['data_results'] : [],
                'applied_tags' => is_array($decoded['tags'] ?? null) ? $decoded['tags'] : [],
                'usage' => $decoded['_usage'] ?? null,
                'evaluated_at' => now(),
            ])->save();

            $run->forceFill([
                'status' => RunStatus::Completed,
                'output' => ['grade' => $grade->value],
                'finished_at' => now(),
            ])->save();

            event(new RunCompleted($run));

            if ($grade === SessionEvaluationGrade::NeedsAttention) {
                $this->notifyAttention($evaluation->fresh());
            }
        } catch (Throwable $e) {
            $evaluation->forceFill(['status' => SessionEvaluationStatus::Failed, 'error' => $e->getMessage()])->save();

            $run->forceFill(['status' => RunStatus::Failed, 'error' => $e->getMessage(), 'finished_at' => now()])->save();

            event(new RunFailed($run));
        }

        return $evaluation->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function judge(AgentSession $session, AgentEvaluationSettings $settings): array
    {
        $agent = $session->agent;
        $transcript = $this->transcriptFor($session);
        [$provider, $model] = $this->resolveProvider($agent, $settings);

        $response = (new SessionEvalJudgeAgent)->prompt(
            SessionEvalJudgeAgent::promptFor($agent, $settings, $transcript),
            provider: $provider,
            model: $model,
        );

        $decoded = json_decode(trim($response->text), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Judge returned an unparseable response.');
        }

        $decoded['_usage'] = $response->usage->toArray();

        return $decoded;
    }

    /**
     * The provider/model to judge `$agent`'s transcript with. `settings.model`
     * is a deliberate, explicit override (grade with a specific model
     * regardless of what generated the answer) and always wins. Otherwise
     * this must match whatever `AgentRunner::resolveProvider()` used to
     * generate the transcript being judged — reading `$agent->provider`/
     * `$agent->model` directly here would silently ignore an agent's
     * `model_catalog_id`, grading against stale columns the run itself
     * never touched.
     *
     * @return array{0: string|array<string, string>, 1: ?string}
     */
    private function resolveProvider(AgentModel $agent, AgentEvaluationSettings $settings): array
    {
        if ($settings->model !== null) {
            return [$agent->provider, $settings->model];
        }

        if ($agent->model_catalog_id === null) {
            return [$agent->provider, $agent->model];
        }

        return [$this->modelCatalog->providerChain($agent->modelCatalog->slug), null];
    }

    private function transcriptFor(AgentSession $session): string
    {
        return $session->messages
            ->map(fn ($message): string => "{$message->role->value}: {$message->content}")
            ->implode("\n");
    }

    private function openRun(AgentSessionEvaluation $evaluation, AgentSession $session): Run
    {
        $run = $evaluation->runs()->create([
            'workspace_id' => $session->workspace_id,
            'trigger_type' => 'session_evaluation',
            'input' => ['agent_session_id' => $session->id],
        ]);

        $run->forceFill(['status' => RunStatus::Running, 'started_at' => now()])->save();

        return $run;
    }

    private function notifyAttention(AgentSessionEvaluation $evaluation): void
    {
        $this->notifications->dispatch(
            $this->notifications->ownersAndAdmins($evaluation->workspace),
            new SessionEvaluationNotifyNotification($evaluation),
        );
    }
}
