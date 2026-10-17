<?php

namespace App\Services\SuperAdmin;

use App\Models\SuperAdmin\SuperAdmin;
use App\Models\SuperAdmin\SuperAdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Records everything that happens to a super admin account and every change
 * made from the super admin panel or its artisan commands. Separate from
 * `AdminAuditLogger` (platform-admin API) on purpose: own table, own model,
 * nothing shared. `$subject` is stored as a snake_case label of its class
 * plus its key — not a morph type — so a new model never needs registering.
 */
class SuperAdminAuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(?SuperAdmin $superAdmin, string $action, ?Model $subject = null, ?array $before = null, ?array $after = null): SuperAdminAuditLog
    {
        return SuperAdminAuditLog::query()->create([
            'super_admin_id' => $superAdmin?->id,
            'action' => $action,
            'subject_type' => $subject !== null ? Str::snake(class_basename($subject)) : null,
            'subject_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
