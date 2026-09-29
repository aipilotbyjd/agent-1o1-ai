<?php

namespace App\Ai\Tools\WorkflowBuilder\Concerns;

use App\Exceptions\WorkflowBuilderConflictException;
use Closure;
use InvalidArgumentException;

/**
 * Shared error handling for the tools that change the draft. A rejected edit
 * goes back to the model as the tool's result, never as an exception, so it
 * can correct itself — including when the user changed the draft on the
 * canvas mid-turn, in which case the session is reloaded first so the next
 * attempt starts from what the user now sees.
 */
trait EditsDraft
{
    /**
     * @param  Closure(): void  $edit
     */
    protected function attemptEdit(Closure $edit, string $confirmation): string
    {
        try {
            $edit();
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        } catch (WorkflowBuilderConflictException) {
            $this->session->refresh();

            return 'The user changed the draft while you were working, so this edit was not applied. Call read_draft to see the current draft, then try again.';
        }

        return $confirmation;
    }
}
