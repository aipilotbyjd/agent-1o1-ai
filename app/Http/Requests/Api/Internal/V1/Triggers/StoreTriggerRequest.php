<?php

namespace App\Http\Requests\Api\Internal\V1\Triggers;

use App\Enums\Triggers\TriggerTargetType;
use App\Enums\Triggers\TriggerType;
use App\Models\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTriggerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_type' => ['required', Rule::enum(TriggerTargetType::class)],
            // Scoped to the route's workspace: without this a member could
            // point a trigger — and therefore a real run, now that
            // `TargetRunStarter` starts one — at another tenant's workflow.
            // `TargetRunStarter` re-checks the same invariant at fire time,
            // since a target can be moved or deleted after creation.
            'target_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists($this->targetModel(), 'id')
                    ->where('workspace_id', $this->workspace()?->id)
                    ->whereNull('deleted_at'),
            ],
            'type' => ['required', Rule::enum(TriggerType::class)],
            'preset_id' => ['nullable', 'integer', 'exists:trigger_presets,id'],
            'config' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'credential_id' => ['nullable', 'integer'],
            'signing_secret' => ['nullable', 'string'],
        ];
    }

    /**
     * The model the `target_id` must exist in — the same class the morph map
     * aliases `target_type` to.
     *
     * @return class-string<Model>
     */
    private function targetModel(): string
    {
        $type = TriggerTargetType::tryFrom((string) $this->input('target_type'));

        // An unknown target_type already fails its own enum rule; falling back
        // to Workflow just keeps this rule from erroring before that message
        // is produced.
        return ($type ?? TriggerTargetType::Workflow)->modelClass();
    }

    private function workspace(): ?Workspace
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace ? $workspace : null;
    }
}
