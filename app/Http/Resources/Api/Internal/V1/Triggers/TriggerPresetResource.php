<?php

namespace App\Http\Resources\Api\Internal\V1\Triggers;

use App\Models\Triggers\TriggerPreset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TriggerPreset
 */
class TriggerPresetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'signature_scheme' => $this->signature_scheme,
            'fields' => $this->fields,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
