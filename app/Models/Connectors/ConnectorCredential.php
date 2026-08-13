<?php

namespace App\Models\Connectors;

use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Connectors\ConnectorCredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A workspace's stored secret for a `Connector` — an OAuth token pair or a
 * manually-entered API key/bearer token/basic-auth pair, depending on the
 * owning `Connector::auth_type`. `data` is encrypted at rest via Laravel's
 * `encrypted:array` cast and is `#[Hidden]` so it never round-trips through
 * `toArray()`/`toJson()` by accident — `ConnectorCredentialResource` must
 * still be relied on for API responses, this is a second guard, not the
 * only one.
 */
#[Fillable(['workspace_id', 'connector_id', 'created_by', 'name', 'data', 'last_used_at', 'expires_at'])]
#[Hidden(['data'])]
class ConnectorCredential extends Model
{
    /** @use HasFactory<ConnectorCredentialFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'encrypted:array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
