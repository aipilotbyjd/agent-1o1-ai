<?php

namespace App\Models\Triggers;

use App\Enums\Triggers\TriggerType;
use App\Models\User;
use App\Models\Workspaces\Workspace;
use Database\Factories\Triggers\TriggerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

#[Fillable([
    'workspace_id', 'target_type', 'target_id', 'type', 'preset_id', 'config',
    'is_active', 'credential_id', 'created_by',
])]
#[Hidden(['signing_secret'])]
class Trigger extends Model
{
    /** @use HasFactory<TriggerFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'consecutive_failure_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TriggerType::class,
            'config' => 'array',
            'signing_secret' => 'encrypted',
            'is_active' => 'boolean',
            'poll_cursor' => 'array',
            'consecutive_failure_count' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function preset(): BelongsTo
    {
        return $this->belongsTo(TriggerPreset::class, 'preset_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TriggerEvent::class);
    }

    /**
     * A fresh 64-character webhook secret. Only meaningful for `TriggerType::Webhook`.
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Increment the circuit breaker's failure streak, disabling the trigger once
     * it crosses the configured threshold.
     *
     * Counted in `failed()` (once per event, not per attempt) — see
     * docs/TRIGGERS_PLAN.md's "design decisions worth keeping" table.
     */
    public function recordFailure(): void
    {
        $this->increment('consecutive_failure_count');

        if ($this->consecutive_failure_count >= (int) config('triggers.failures_before_disable')) {
            // forceFill: is_active is intentionally mass-assignable via the API
            // (see #[Fillable]), but this specific write is the circuit
            // breaker's own decision, not a user request, so it bypasses that
            // check the same way the fields below do.
            $this->forceFill(['is_active' => false])->save();
        }
    }

    /**
     * A success clears the streak — only consecutive failures trip the breaker.
     *
     * forceFill: `consecutive_failure_count`/`last_run_at` are deliberately
     * absent from #[Fillable] — they're system bookkeeping, not something an
     * API consumer should set directly — so a plain update() would silently
     * drop them.
     */
    public function recordSuccess(): void
    {
        $this->forceFill(['consecutive_failure_count' => 0, 'last_run_at' => now()])->save();
    }
}
