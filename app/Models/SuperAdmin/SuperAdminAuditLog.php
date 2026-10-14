<?php

namespace App\Models\SuperAdmin;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing a super admin did or that happened to a super admin account —
 * see `SuperAdminAuditLogger`. Append-only: there is no `updated_at` and
 * nothing edits a row once written. Kept apart from `AdminAuditLog`, which
 * belongs to the platform-admin API.
 */
#[Fillable(['super_admin_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'ip_address'])]
class SuperAdminAuditLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class);
    }
}
