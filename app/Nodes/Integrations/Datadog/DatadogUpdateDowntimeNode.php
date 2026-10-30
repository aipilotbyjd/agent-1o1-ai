<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogUpdateDowntimeNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_update_downtime';
    }

    public function name(): string
    {
        return 'Datadog: Update Downtime';
    }

    public function description(): string
    {
        return 'Changes the settings filled in here on a downtime.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['downtime_id'],
            'properties' => [
                ...$this->credentialFields(),
                'downtime_id' => Field::text('Downtime id', 'From List Downtimes.'),
                'scope' => Field::text('Scope', 'Which groups to silence, like env:prod. * is everything.', 'env:prod'),
                'monitor_id' => $this->monitorField('Only this monitor'),
                'monitor_tags' => Field::list('Or monitors with these tags', 'Like team:payments.'),
                'start' => Field::datetime('Start', 'Default now.'),
                'end' => Field::datetime('End', 'Empty lasts until it is cancelled.'),
                'message' => Field::textarea('Message'),
                'display_timezone' => Field::advanced(Field::text('Display time zone', null, 'Europe/London')),
                'mute_first_recovery_notification' => Field::advanced(Field::boolean('Mute the first recovery notification')),
                'schedule' => Field::advanced(Field::json('Recurring schedule', 'As in the API. Used instead of Start and End.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $identifier = filled($config['monitor_id'] ?? null)
            ? ['monitor_id' => (int) $this->numericId($config, 'monitor_id', 'The monitor')]
            : ($this->listOf($config, 'monitor_tags') !== [] ? ['monitor_tags' => $this->listOf($config, 'monitor_tags')] : null);
        $schedule = $this->jsonOf($config, 'schedule', 'Schedule') ?? array_filter(['start' => $this->text($config, 'start'), 'end' => $this->text($config, 'end')], fn (mixed $value): bool => $value !== null);
        $attributes = array_filter([
            'scope' => $this->text($config, 'scope'),
            'message' => $this->text($config, 'message'),
            'monitor_identifier' => $identifier,
            'schedule' => $schedule ?: null,
            'display_timezone' => $this->text($config, 'display_timezone'),
            'mute_first_recovery_notification' => ($config['mute_first_recovery_notification'] ?? false) ? true : null,
        ], fn (mixed $value): bool => $value !== null);

        $id = $this->pathId($config, 'downtime_id', 'The downtime id');

        if ($attributes === []) {
            throw new InvalidArgumentException('Fill in something to change.');
        }

        return $this->flatten((array) ($this->datadog($run, $config, 'PATCH', "/api/v2/downtime/{$id}", [], ['data' => ['type' => 'downtime', 'id' => rawurldecode($id), 'attributes' => $attributes]])['data'] ?? []));
    }
}
