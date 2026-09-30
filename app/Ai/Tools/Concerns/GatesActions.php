<?php

namespace App\Ai\Tools\Concerns;

use App\Enums\Agents\ActionEffect;
use App\Services\Agents\Approvals\ActionGuard;
use Closure;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Tools\Request;

/**
 * Puts a tool behind an agent's approvals (`Services\Agents\Approvals`).
 * `ToolRegistry` attaches the guard; a tool built without one — directly in
 * a test, or anywhere outside an agent turn — runs exactly as it did before
 * approvals existed.
 *
 * The using tool supplies three things: which of the request's arguments the
 * model may set (`actionArguments()`), what the call would really run with
 * (`effectiveArguments()`), and what running it does (`actionEffect()`).
 */
trait GatesActions
{
    use InteractsWithApprovals;

    private ?ActionGuard $actionGuard = null;

    public function guardedBy(?ActionGuard $guard): static
    {
        $this->actionGuard = $guard;

        return $this;
    }

    public function actionGuard(): ?ActionGuard
    {
        return $this->actionGuard;
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function actionArguments(Request $request): array;

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    abstract protected function effectiveArguments(array $arguments): array;

    /**
     * @param  array<string, mixed>  $effectiveArguments
     */
    abstract protected function actionEffect(array $effectiveArguments): ActionEffect;

    protected function needsApproval(Request $request): Approval|bool
    {
        if ($this->actionGuard === null) {
            return false;
        }

        $arguments = $this->actionArguments($request);
        $effective = $this->effectiveArguments($arguments);

        return $this->actionGuard->approvalFor($request, $arguments, $effective, $this->actionEffect($effective));
    }

    /**
     * Runs `$execute` if the gate lets this call through, otherwise returns
     * what the model should be told instead.
     *
     * @param  Closure(array<string, mixed>): string  $execute  given the (possibly edited) model arguments
     */
    protected function guarded(Request $request, Closure $execute): string
    {
        $arguments = $this->actionArguments($request);

        if ($this->actionGuard === null) {
            return $execute($arguments);
        }

        $effective = $this->effectiveArguments($arguments);

        return $this->actionGuard->run($request, $arguments, $effective, $this->actionEffect($effective), $execute);
    }
}
