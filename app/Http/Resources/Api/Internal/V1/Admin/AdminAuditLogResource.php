<?php

namespace App\Http\Resources\Api\Internal\V1\Admin;

use App\Models\Admin\AdminAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AdminAuditLog
 */
class AdminAuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'admin' => $this->whenLoaded('admin', fn () => $this->admin === null ? null : [
                'id' => $this->admin->id,
                'name' => $this->admin->name,
                'email' => $this->admin->email,
            ]),
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'before' => $this->before,
            'after' => $this->after,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at,
        ];
    }
}
