<?php

namespace App\Http\Resources\Api\Internal\V1\Workflows;

use App\Models\Workflows\WorkflowPageSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One use of a page, for its history. A visitor at the public link has no
 * user, and is shown as such.
 *
 * @mixin WorkflowPageSubmission
 */
class WorkflowPageSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'is_test' => $this->is_test,
            'user' => $this->user === null ? null : ['id' => $this->user->id, 'name' => $this->user->name],
            'run' => $this->run === null ? null : [
                'id' => $this->run->id,
                'status' => $this->run->status->value,
                'started_at' => $this->run->started_at,
                'finished_at' => $this->run->finished_at,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
