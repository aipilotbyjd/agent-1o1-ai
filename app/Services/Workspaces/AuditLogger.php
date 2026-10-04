<?php

namespace App\Services\Workspaces;

use App\Enums\Workspaces\AuditAction;
use App\Models\Auth\ApiKey;
use App\Models\Workspaces\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the workspace audit trail.
 *
 * The actor is whoever the current request belongs to — the signed-in user,
 * or the API key on the public API — and `system` outside a request (queue
 * jobs, webhooks, console). The request is resolved per call, not injected:
 * the router caches controller instances, so anything held from a constructor
 * would describe whichever request built it first.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata  Names and ids only — never a secret value.
     */
    public function record(string $workspaceId, AuditAction $action, ?Model $subject = null, array $metadata = []): AuditLog
    {
        $request = request();
        $user = $request->user();
        $apiKey = $request->attributes->get('api_key');

        return AuditLog::query()->create([
            'workspace_id' => $workspaceId,
            'actor_id' => $user?->getKey(),
            'actor_label' => match (true) {
                $user !== null => (string) $user->email,
                $apiKey instanceof ApiKey => "api_key:{$apiKey->name}",
                default => 'system',
            },
            'action' => $action,
            'subject_type' => $subject === null ? null : class_basename($subject),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
        ]);
    }
}
