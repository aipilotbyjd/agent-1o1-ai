<?php

namespace App\Http\Resources\Api\Internal\V1\Agents;

use App\Models\Agents\SkillSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SkillSource
 */
class SkillSourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'repo' => $this->repo,
            'branch' => $this->branch,
            'path' => $this->path,
            'url' => $this->url(),
            'is_shared' => $this->is_shared,
            'credential_id' => $this->connector_credential_id,
            'account' => $this->credential?->name,
            'status' => $this->status->value,
            'last_error' => $this->last_error,
            'last_commit_sha' => $this->last_commit_sha,
            'last_synced_at' => $this->last_synced_at,
            'skills_count' => $this->skills_count,
            'created_at' => $this->created_at,
        ];
    }
}
