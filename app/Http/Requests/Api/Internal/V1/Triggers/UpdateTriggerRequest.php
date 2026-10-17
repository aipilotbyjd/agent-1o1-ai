<?php

namespace App\Http\Requests\Api\Internal\V1\Triggers;

use App\Enums\Agents\AutonomyMode;
use App\Enums\Triggers\WebhookAuthentication;
use App\Models\Triggers\Trigger;
use App\Rules\SafeOutboundUrl;
use App\Rules\Timezone;
use Closure;
use Cron\CronExpression;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTriggerRequest extends FormRequest
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
            'config' => ['nullable', 'array'],
            // For an agent target: the mode its triggered turns run under, overriding the agent's own.
            'config.autonomy_mode' => ['nullable', Rule::enum(AutonomyMode::class)],
            'config.test_mode' => ['nullable', 'boolean'],
            // A schedule trigger's cron expression: the scheduler parses it every
            // minute, so an invalid one must never get stored.
            'config.cron' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! CronExpression::isValidExpression((string) $value)) {
                    $fail('The cron expression is not valid.');
                }
            }],
            'config.url' => ['nullable', 'string', new SafeOutboundUrl],
            'config.timezone' => ['nullable', 'string', new Timezone],
            'config.authentication' => ['nullable', Rule::enum(WebhookAuthentication::class)],
            // An HTTP header name: letters, digits and dashes.
            'config.header_name' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'config.if_already_running' => ['nullable', 'string', Rule::in(['queue', 'skip'])],
            'config.max_failures' => ['nullable', 'integer', 'between:'.implode(',', config('triggers.max_failures_range'))],
            'is_active' => ['nullable', 'boolean'],
            // Empty string clears it: the webhook then accepts unsigned requests again.
            'signing_secret' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * A trigger that belongs to a canvas node is configured on the canvas:
     * the next publish would overwrite an edit made here (`TriggerNodeSync`).
     *
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $trigger = $this->route('trigger');

                if ($trigger instanceof Trigger && $trigger->trigger_node_key !== null && $this->has('config')) {
                    $validator->errors()->add('config', 'This trigger is part of the workflow. Edit it on the canvas, then publish.');
                }
            },
        ];
    }
}
