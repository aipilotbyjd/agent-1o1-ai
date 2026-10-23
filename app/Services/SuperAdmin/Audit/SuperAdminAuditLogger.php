<?php

namespace App\Services\SuperAdmin\Audit;

use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Records every change a super admin makes, from the API or the command
 * line, so "who gave this workspace a year of Pro?" always has an answer.
 *
 * `$subject` is stored as a snake_case label of its class plus its key (not
 * a morph type) so a new model never needs registering. Recording an entry
 * also flags the current request as audited, which tells
 * `AuditSuperAdminRequest` not to add its generic fallback row.
 */
class SuperAdminAuditLogger
{
    public const AUDITED_ATTRIBUTE = 'super_admin_audited';

    public const REQUEST_ID_ATTRIBUTE = 'super_admin_request_id';

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        ?SuperAdmin $actor,
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?string $actorLabel = null,
    ): SuperAdminAuditLog {
        $request = request();
        $overHttp = $request->route() !== null;

        $request->attributes->set(self::AUDITED_ATTRIBUTE, true);

        return SuperAdminAuditLog::query()->create([
            'super_admin_id' => $actor?->id,
            'actor_label' => $actorLabel ?? ($actor === null ? 'system' : null),
            'action' => $action,
            'subject_type' => $subject !== null ? Str::snake(class_basename($subject)) : null,
            'subject_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'ip_address' => $overHttp ? $request->ip() : null,
            'user_agent' => $overHttp ? Str::limit((string) $request->userAgent(), 500, '') : null,
            'request_id' => $overHttp ? $this->requestId($request) : null,
        ]);
    }

    /**
     * Records an update, keeping only the attributes that actually changed so
     * the log reads as a diff rather than two full snapshots. Returns null
     * (and writes nothing) when nothing changed.
     *
     * @param  array<string, mixed>  $original
     */
    public function recordChanges(?SuperAdmin $actor, string $action, Model $subject, array $original, ?string $reason = null, ?string $actorLabel = null): ?SuperAdminAuditLog
    {
        $changes = collect($subject->getChanges())->except(['updated_at']);

        if ($changes->isEmpty()) {
            return null;
        }

        return $this->record(
            $actor,
            $action,
            $subject,
            $changes->mapWithKeys(fn (mixed $value, string $key): array => [$key => $original[$key] ?? null])->all(),
            $changes->all(),
            $reason,
            $actorLabel,
        );
    }

    private function requestId(\Illuminate\Http\Request $request): ?string
    {
        $id = $request->headers->get('X-Request-Id') ?? $request->attributes->get(self::REQUEST_ID_ATTRIBUTE);

        return is_string($id) && $id !== '' ? Str::limit($id, 64, '') : null;
    }
}
