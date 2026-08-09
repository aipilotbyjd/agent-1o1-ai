<?php

namespace App\Models\Triggers;

use App\Enums\Triggers\TriggerType;
use Database\Factories\Triggers\TriggerPresetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'category', 'key', 'name', 'description', 'type', 'signature_scheme',
    'dedupe_header', 'dedupe_payload_path', 'config', 'fields', 'is_active', 'sort_order',
])]
class TriggerPreset extends Model
{
    /** @use HasFactory<TriggerPresetFactory> */
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
            'type' => TriggerType::class,
            'config' => 'array',
            'fields' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function triggers(): HasMany
    {
        return $this->hasMany(Trigger::class, 'preset_id');
    }
}
