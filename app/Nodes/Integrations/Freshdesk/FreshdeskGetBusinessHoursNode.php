<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetBusinessHoursNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_business_hours';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Business Hours';
    }

    public function description(): string
    {
        return 'Reads one set of business hours.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['business_hours_id'],
            'properties' => [
                ...$this->credentialFields(),
                'business_hours_id' => Field::text('Business hours id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'business_hours_id', 'The business hours');

        return $this->findOne($run, $config, "/business_hours/{$id}", $id);
    }
}
