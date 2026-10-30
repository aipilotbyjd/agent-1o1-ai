<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogUpdateSloCorrectionNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_update_slo_correction';
    }

    public function name(): string
    {
        return 'Datadog: Update SLO Correction';
    }

    public function description(): string
    {
        return 'Changes the settings filled in here on an SLO correction.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['correction_id'],
            'properties' => [
                ...$this->credentialFields(),
                'correction_id' => Field::text('Correction id'),
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

        $id = $this->pathId($config, 'correction_id', 'The correction id');

        if ($attributes === []) {
            throw new InvalidArgumentException('Fill in something to change.');
        }

        return $this->flatten((array) ($this->datadog($run, $config, 'PATCH', "/api/v1/slo/correction/{$id}", [], ['data' => ['type' => 'correction', 'attributes' => $attributes]])['data'] ?? []));
    }
}
