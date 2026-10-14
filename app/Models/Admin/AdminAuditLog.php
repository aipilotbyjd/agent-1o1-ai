<?php

namespace App\Models\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change a platform admin made — see `AdminAuditLogger`. Append-only:
 * there is no `updated_at` and nothing edits a row once written.
 */
#[Fillable(['admin_user_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'ip_address'])]
class AdminAuditLog extends Model
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

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
