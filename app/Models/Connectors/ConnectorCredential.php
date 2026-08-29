<?php

namespace App\Models\Connectors;

use App\Enums\Connectors\ConnectorCredentialScope;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Connectors\ConnectorCredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * A workspace's stored secret for a `Connector` — an OAuth token pair or a
 * manually-entered API key/bearer token/basic-auth pair, depending on the
 * owning `Connector::auth_type`. `data` is encrypted at rest via Laravel's
 * `encrypted:array` cast and is `#[Hidden]` so it never round-trips through
 * `toArray()`/`toJson()` by accident — `ConnectorCredentialResource` must
 * still be relied on for API responses, this is a second guard, not the
 * only one.
 *
 * `scope` + `is_default` back Gumloop's Personal/Team credentials and
 * default-account resolution — see `ConnectorCredentialScope` and
 * `Nodes\Integrations\Concerns\ResolvesConnectorCredential`.
 */
#[Fillable(['workspace_id', 'connector_id', 'created_by', 'scope', 'is_default', 'name', 'data', 'last_used_at', 'expires_at'])]
#[Hidden(['data'])]
class ConnectorCredential extends Model
{
    /** @use HasFactory<ConnectorCredentialFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'scope' => 'team',
        'is_default' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => ConnectorCredentialScope::class,
            'is_default' => 'boolean',
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

    /**
     * Team credentials are visible to any workspace member with
     * `connector.view`; a personal credential is visible only to whoever
     * created it — "Privacy guaranteed: Even in teams, other members
     * cannot see or use your personal connectors."
     */
    public function isVisibleTo(User $user): bool
    {
        return $this->scope === ConnectorCredentialScope::Team || $this->created_by === $user->id;
    }

    /**
     * @param  Builder<ConnectorCredential>  $query
     * @return Builder<ConnectorCredential>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q
            ->where('scope', ConnectorCredentialScope::Team->value)
            ->orWhere('created_by', $user->id));
    }

    /**
     * Marks this credential as the default for its (workspace, connector,
     * scope[, creator for a personal credential]) group, unsetting any
     * sibling default in the same group first — at most one default per
     * group at a time.
     */
    public function markAsDefault(): void
    {
        DB::transaction(function () {
            static::query()
                ->where('workspace_id', $this->workspace_id)
                ->where('connector_id', $this->connector_id)
                ->where('scope', $this->scope->value)
                ->when(
                    $this->scope === ConnectorCredentialScope::Personal,
                    fn ($query) => $query->where('created_by', $this->created_by),
                )
                ->where('id', '!=', $this->id)
                ->update(['is_default' => false]);

            $this->forceFill(['is_default' => true])->save();
        });
    }
}
