<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskListTagsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_list_tags';
    }

    public function name(): string
    {
        return 'Zendesk: List Tags';
    }

    public function description(): string
    {
        return 'Lists the account\'s most used tags with their counts.';
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
                'limit' => Field::integer('Max results', null, 100, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listing($run, $config, '/tags', 'tags');
    }
}
