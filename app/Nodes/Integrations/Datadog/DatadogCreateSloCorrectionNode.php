<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogCreateSloCorrectionNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_create_slo_correction';
    }

    public function name(): string
    {
        return 'Datadog: Create SLO Correction';
    }

    public function description(): string
    {
        return 'Tells an objective to ignore a time window, such as planned maintenance.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['slo_id', 'category', 'start'],
            'properties' => [
                ...$this->credentialFields(),
                'slo_id' => $this->sloField(),
                'category' => Field::select('Category', ['Scheduled Maintenance' => 'Scheduled maintenance', 'Outside Business Hours' => 'Outside business hours', 'Deployment' => 'Deployment', 'Other' => 'Other'], null, 'Scheduled Maintenance'),
                'start' => Field::datetime('Start'),
                'end' => Field::datetime('End'),
                'description' => Field::textarea('Description'),
                'timezone' => Field::advanced(Field::text('Time zone', null, 'UTC')),
                'rrule' => Field::advanced(Field::text('Repeat rule', 'An RRULE, like FREQ=DAILY;INTERVAL=1.')),
                'duration' => Field::advanced(Field::integer('Duration', 'Seconds, for a repeating correction.', null, 1)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $attributes = array_filter([
            'category' => $this->text($config, 'category'),
            'start' => filled($config['start'] ?? null) ? $this->unixTime($config['start'], 'Start') : null,
            'end' => filled($config['end'] ?? null) ? $this->unixTime($config['end'], 'End') : null,
            'description' => $this->text($config, 'description'),
            'timezone' => $this->text($config, 'timezone'),
            'rrule' => $this->text($config, 'rrule'),
            'duration' => filled($config['duration'] ?? null) ? (int) $config['duration'] : null,
        ], fn (mixed $value): bool => $value !== null);

        $attributes['slo_id'] = $this->requiredString($config, 'slo_id', 'The SLO');

        if (! isset($attributes['category'], $attributes['start'])) {
            throw new InvalidArgumentException('The category and the start are required.');
        }

        return $this->flatten((array) ($this->datadog($run, $config, 'POST', '/api/v1/slo/correction', [], ['data' => ['type' => 'correction', 'attributes' => $attributes]])['data'] ?? []));
    }
}
