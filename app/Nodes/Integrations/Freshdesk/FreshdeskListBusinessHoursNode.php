<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskListBusinessHoursNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_business_hours';
    }

    public function name(): string
    {
        return 'Freshdesk: List Business Hours';
    }

    public function description(): string
    {
        return 'Lists the account\'s business hours.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listing($run, $config, '/business_hours', 'business_hours');
    }
}
