<?php

namespace App\Http\Requests\Api\Internal\V1\Triggers;

use App\Enums\Agents\AutonomyMode;
use App\Rules\SafeOutboundUrl;
use Closure;
use Cron\CronExpression;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
