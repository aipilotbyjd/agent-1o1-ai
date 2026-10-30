<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskFindContactNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_find_contact';
    }

    public function name(): string
    {
        return 'Freshdesk: Find Contact';
    }

    public function description(): string
    {
        return 'Finds contacts by the start of their name or email.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['term'],
            'properties' => [
                ...$this->credentialFields(),
                'term' => Field::text('Name or email starts with', null, 'ada'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->freshdesk($run, $config, 'GET', '/contacts/autocomplete', ['term' => $this->requiredString($config, 'term', 'The text')]);

        return ['contacts' => array_values($rows), 'count' => count($rows)];
    }
}
