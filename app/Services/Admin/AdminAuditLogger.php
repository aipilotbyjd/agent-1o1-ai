<?php

namespace App\Services\Admin;

use App\Models\Admin\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Records every change a platform admin makes, from the admin API or its
 * artisan equivalents, so "who gave this workspace a year of Pro?" always
 * has an answer. `$subject` is stored as a snake_case label of its class
 * plus its key — not a morph type — so a new model never needs registering.
 */
class AdminAuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(?User $admin, string $action, ?Model $subject = null, ?array $before = null, ?array $after = null): AdminAuditLog
    {
        return AdminAuditLog::query()->create([
            'admin_user_id' => $admin?->id,
            'action' => $action,
            'subject_type' => $subject !== null ? Str::snake(class_basename($subject)) : null,
            'subject_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    /**
     * Records an update, keeping only the attributes that actually changed
     * so the log reads as a diff rather than two full snapshots.
     *
     * @param  array<string, mixed>  $original
     */
    public function recordChanges(?User $admin, string $action, Model $subject, array $original): ?AdminAuditLog
    {
        $changes = collect($subject->getChanges())->except(['updated_at']);

        if ($changes->isEmpty()) {
            return null;
        }

        return $this->record(
            $admin,
            $action,
            $subject,
            $changes->keys()->mapWithKeys(fn (string $key): array => [$key => $original[$key] ?? null])->all(),
            $changes->all(),
        );
    }
}
