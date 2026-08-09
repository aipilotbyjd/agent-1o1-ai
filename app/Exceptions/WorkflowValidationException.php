<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by `Workflow::replaceGraph()` (config-schema issues only) and
 * `Workflow::publishVersion()` (full `GraphValidator` run) — mapped to a 422
 * response in `bootstrap/app.php`. See docs/WORKFLOWS_PLAN.md's "Validation"
 * section for the checks that can produce these errors.
 */
class WorkflowValidationException extends RuntimeException
{
    /**
     * @param  array<int, string>  $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('The workflow graph is invalid.');
    }

    /**
     * @return array<int, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
