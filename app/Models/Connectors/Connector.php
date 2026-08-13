<?php

namespace App\Models\Connectors;

use App\Enums\Connectors\ConnectorAuthType;
use Database\Factories\Connectors\ConnectorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The integration catalog (Slack, GitHub, Gmail, ...) — one row per
 * `NodeContract::category()` that needs a credential. `fields` is the
 * form schema a manual (`api_key`/`bearer_token`/`basic_auth`) credential's
 * `data` must satisfy; `oauth` carries the authorize/token URLs and default
 * scopes for `auth_type = oauth2` connectors. See docs/PLAN.md Phase 6.
 */
#[Fillable(['key', 'name', 'description', 'icon', 'color', 'auth_type', 'fields', 'oauth', 'is_active', 'sort_order'])]
class Connector extends Model
{
    /** @use HasFactory<ConnectorFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auth_type' => ConnectorAuthType::class,
            'fields' => 'array',
            'oauth' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function isOAuth(): bool
    {
        return $this->auth_type === ConnectorAuthType::OAuth2;
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(ConnectorCredential::class);
    }
}
