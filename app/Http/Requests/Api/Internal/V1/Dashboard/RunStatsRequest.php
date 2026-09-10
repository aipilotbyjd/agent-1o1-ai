<?php

namespace App\Http\Requests\Api\Internal\V1\Dashboard;

/**
 * Adds the one drill-down the run-stats chart needs: the same numbers for a
 * single workflow rather than the whole workspace.
 */
class RunStatsRequest extends DashboardWindowRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'workflow_id' => ['nullable', 'integer'],
        ];
    }

    public function workflowId(): ?int
    {
        $workflowId = $this->validated('workflow_id');

        return $workflowId === null ? null : (int) $workflowId;
    }
}
