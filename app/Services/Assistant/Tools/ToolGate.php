<?php

namespace App\Services\Assistant\Tools;

use App\Ai\Assistant\Tools\AssistantTool;
use App\Enums\Assistant\AssistantActionStatus;
use App\Enums\Assistant\AssistantToolRule;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Assistant\AssistantAction;
use App\Models\Assistant\AssistantToolRule as ToolRuleModel;
use App\Models\Assistant\AssistantTurn;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Tools\Request;
use Throwable;

/**
 * Decides, per call, whether a tool runs now or waits for the owner, and
 * makes every call that needed approval run exactly once: its outcome is
 * stored on the `AssistantAction`, and any replay of the same call (the
 * SDK re-checks calls when a paused turn resumes) gets that stored result.
 */
class ToolGate
{
    /**
     * @param  Collection<string, AssistantToolRule>  $rules  tool name => the owner's rule
     */
    public function __construct(
        private readonly AssistantTurn $turn,
        private readonly Collection $rules,
    ) {}

    public static function forTurn(AssistantTurn $turn): self
    {
        $rules = ToolRuleModel::query()
            ->where('assistant_id', $turn->session->assistant_id)
            ->get()
            ->mapWithKeys(fn (ToolRuleModel $rule): array => [$rule->tool => $rule->rule]);

        return new self($turn, $rules);
    }

    public function ruleFor(AssistantTool $tool): AssistantToolRule
    {
        $rule = $this->rules->get($tool->name()) ?? $tool->effect()->defaultRule();

        return $rule === AssistantToolRule::Allow && ! $tool->effect()->canBeAutoAllowed()
            ? AssistantToolRule::Ask
            : $rule;
    }

    public function approvalFor(AssistantTool $tool, Request $request): Approval|false
    {
        if ($this->ruleFor($tool) !== AssistantToolRule::Ask) {
            return false;
        }

        $existing = $this->actionFor($request);

        // Already decided (or run) — a resumed turn re-checks the call.
        if ($existing !== null && $existing->status !== AssistantActionStatus::Pending) {
            return false;
        }

        $reason = $tool->approvalReason($request);

        AssistantAction::query()->firstOrCreate(
            ['assistant_session_id' => $this->turn->assistant_session_id, 'tool_call_id' => (string) $request->toolCallId()],
            [
                'assistant_turn_id' => $this->turn->id,
                'tool' => $tool->name(),
                'arguments' => $request->all(),
                'effect' => $tool->effect(),
                'reason' => $reason,
                'expires_at' => now()->addMinutes((int) config('assistant.approvals.ttl_minutes')),
            ],
        );

        return Approval::required($reason);
    }

    /**
     * Runs the call — or returns the stored outcome if it already ran.
     *
     * A failing call is reported back to the model as its result rather
     * than thrown, so it can fix its arguments or tell the owner what went
     * wrong; running out of credits still ends the turn.
     *
     * @param  Closure(): string  $execute
     */
    public function run(AssistantTool $tool, Request $request, Closure $execute): string
    {
        $action = $this->actionFor($request);

        if ($action !== null && $action->status->hasRun()) {
            return (string) $action->result;
        }

        if ($action !== null && $action->status === AssistantActionStatus::Rejected) {
            return $this->rejectionText($action);
        }

        try {
            $result = $execute();
            $action?->forceFill(['status' => AssistantActionStatus::Executed, 'result' => $result])->save();

            return $result;
        } catch (InsufficientCreditsException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            $result = json_encode([
                'error' => $e instanceof QueryException ? 'An internal error occurred.' : $e->getMessage(),
                'note' => 'The tool call failed. Check the arguments and try again, or tell the person what went wrong.',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';

            $action?->forceFill(['status' => AssistantActionStatus::Failed, 'result' => $result])->save();

            return $result;
        }
    }

    public function rejectionText(AssistantAction $action): string
    {
        $base = $action->status === AssistantActionStatus::Expired
            ? 'Nobody approved this action in time, so it was not run.'
            : 'The person declined this action, so it was not run.';

        return filled($action->decision_note) ? "{$base} Their note: {$action->decision_note}" : $base;
    }

    private function actionFor(Request $request): ?AssistantAction
    {
        if ($request->toolCallId() === null) {
            return null;
        }

        return AssistantAction::query()
            ->where('assistant_session_id', $this->turn->assistant_session_id)
            ->where('tool_call_id', $request->toolCallId())
            ->first();
    }
}
