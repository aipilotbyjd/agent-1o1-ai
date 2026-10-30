<?php

namespace App\Nodes\Integrations\Pinterest;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class PinterestPinAnalyticsNode extends AbstractPinterestNode
{
    public function type(): string
    {
        return 'pinterest_pin_analytics';
    }

    public function name(): string
    {
        return 'Pinterest: Get Pin Analytics';
    }

    public function description(): string
    {
        return 'Reads how a pin performed over a period: impressions, saves and clicks.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['pin_id'],
            'properties' => [
                ...$this->credentialFields(),
                'pin_id' => Field::text('Pin id', 'An id from a previous step (a long number).'),
                'days' => Field::integer('Last days', 'The period ends yesterday.', 30, 1, 90),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'pin_id', 'The pin');
        $days = max(1, min(90, (int) ($config['days'] ?? 30)));
        $end = Carbon::now()->utc()->subDay();

        $analytics = $this->pinterest($run, $config, 'GET', "/pins/{$id}/analytics", [
            'start_date' => $end->copy()->subDays($days - 1)->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'metric_types' => 'IMPRESSION,SAVE,PIN_CLICK,OUTBOUND_CLICK',
        ]);

        return ['pin_id' => $id, 'days' => $days, 'metrics' => (array) ($analytics['all']['summary_metrics'] ?? [])];
    }
}
