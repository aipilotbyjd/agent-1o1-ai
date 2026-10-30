<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskAddTagsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_add_tags';
    }

    public function name(): string
    {
        return 'Zendesk: Add Tags';
    }

    public function description(): string
    {
        return 'Adds tags to a ticket, person or organization and keeps the ones it has.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['resource', 'resource_id', 'tags'],
            'properties' => [
                ...$this->credentialFields(),
                'resource' => Field::select('On', ['tickets' => 'Ticket', 'users' => 'Person', 'organizations' => 'Organization'], null, 'tickets'),
                'resource_id' => Field::text('Id'),
                'tags' => Field::text('Tags', 'Separated by commas.', 'vip, billing'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->changeTags($run, $config, 'PUT');
    }
}
