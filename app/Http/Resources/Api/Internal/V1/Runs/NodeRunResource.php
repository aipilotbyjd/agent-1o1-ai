<?php

namespace App\Http\Resources\Api\Internal\V1\Runs;

use App\Models\Runs\NodeRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NodeRun
 */
class NodeRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'type' => $this->type,
            'status' => $this->status->value,
            'output' => $this->output,
            'error' => $this->error,
            'attempt' => $this->attempt,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
        ];
    }
}
