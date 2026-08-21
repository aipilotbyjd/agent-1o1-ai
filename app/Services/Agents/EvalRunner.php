<?php

namespace App\Services\Agents;

use App\Enums\Agents\EvalRunStatus;
use App\Enums\RunStatus;
use App\Events\Runs\RunCompleted;
use App\Events\Runs\RunFailed;
use App\Models\Agents\AgentEvalCase;
use App\Models\Agents\AgentEvalCaseResult;
use App\Models\Agents\AgentEvalRun;
use App\Models\Agents\AgentEvalSuite;
use App\Models\Runs\Run;
use App\Models\User;
use App\Services\Billing\CreditGate;
use Throwable;

/**
 * Runs a suite against its agent and records how every case graded.
 *
 * Each case is a fresh, stateless turn (`AgentRunner::ask()`), never a shared
 * conversation: cases must not be able to influence one another, or a suite's
 * result would depend on the order its cases happen to be in.
 *
 * **Cases execute for real, tools included.** That is the point — an eval of
 * an agent whose tools were stubbed would grade a different agent than the
 * one in production — but it does mean a suite aimed at a tool-using agent
 * can cause real side effects, exactly as chatting with that agent would.
 * Suites for such agents should be written against read-only prompts.
 *
 * The whole grading pass is recorded as one `Run` (`runnable_type =
 * AgentEvalRun`), which is what puts eval spend on the same ledger as
 * everything else — see `RecordRunCreditUsage`.
 */
class EvalRunner
{
    public function __construct(
        private readonly AgentRunner $agentRunner,
        private readonly AgentVersioner $versioner,
        private readonly AssertionGrader $grader,
        private readonly CreditGate $creditGate,
    ) {}

    public function run(AgentEvalSuite $suite, ?User $triggeredBy = null): AgentEvalRun
    {
        $agent = $suite->agent;

        $this->creditGate->assertCanStartRun($suite->workspace);

        $evalRun = $suite->runs()->create([
            'workspace_id' => $suite->workspace_id,
            // Records which behavior was graded — see the migration.
            'agent_version_id' => $this->versioner->currentVersion($agent)->id,
            'triggered_by' => $triggeredBy?->id,
        ]);

        $evalRun->forceFill(['status' => EvalRunStatus::Running, 'started_at' => now()])->save();

        $run = $this->openRun($evalRun, $suite, $triggeredBy);

        try {
            [$passed, $failed] = $this->gradeCases($suite, $evalRun, $run);

            $evalRun->forceFill([
                'status' => EvalRunStatus::Completed,
                'passed' => $passed,
                'failed' => $failed,
                'finished_at' => now(),
            ])->save();

            $run->forceFill([
                'status' => RunStatus::Completed,
                'output' => ['passed' => $passed, 'failed' => $failed],
                'finished_at' => now(),
            ])->save();

            event(new RunCompleted($run));
        } catch (Throwable $e) {
            // Only something outside an individual case can land here — a
            // single case's failure is caught per case and recorded as a
            // failing result, since one broken case shouldn't discard the
            // evidence from all the others.
            $evalRun->forceFill([
                'status' => EvalRunStatus::Failed,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ])->save();

            $run->forceFill([
                'status' => RunStatus::Failed,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ])->save();

            event(new RunFailed($run));
        }

        return $evalRun->fresh();
    }

    /**
     * @return array{0: int, 1: int} passed and failed case counts
     */
    private function gradeCases(AgentEvalSuite $suite, AgentEvalRun $evalRun, Run $run): array
    {
        $passed = 0;
        $failed = 0;

        foreach ($suite->cases as $case) {
            $result = $this->gradeCase($case, $evalRun, $run);

            $result->passed ? $passed++ : $failed++;
        }

        return [$passed, $failed];
    }

    private function gradeCase(AgentEvalCase $case, AgentEvalRun $evalRun, Run $run): AgentEvalCaseResult
    {
        $result = $evalRun->results()->create([
            'agent_eval_case_id' => $case->id,
        ]);

        try {
            $answer = $this->agentRunner->ask($evalRun->suite->agent, $run, $case->input);
        } catch (Throwable $e) {
            $result->forceFill(['passed' => false, 'error' => $e->getMessage()])->save();

            return $result;
        }

        $graded = array_map(
            fn (array $assertion): array => $this->grader->grade($assertion, $answer['text']),
            $case->assertions ?? [],
        );

        $result->forceFill([
            'output' => $answer['text'],
            'usage' => $answer['usage'],
            'assertions' => $graded,
            // A case with no assertions can't fail, but it also proves
            // nothing — the API refuses to create one, so this only guards
            // against rows written before that rule existed.
            'passed' => ! in_array(false, array_column($graded, 'passed'), true),
        ])->save();

        return $result;
    }

    private function openRun(AgentEvalRun $evalRun, AgentEvalSuite $suite, ?User $triggeredBy): Run
    {
        $run = $evalRun->runs()->create([
            'workspace_id' => $suite->workspace_id,
            'trigger_type' => 'eval',
            'input' => ['suite_id' => $suite->id],
            'triggered_by' => $triggeredBy?->id,
        ]);

        $run->forceFill(['status' => RunStatus::Running, 'started_at' => now()])->save();

        return $run;
    }
}
