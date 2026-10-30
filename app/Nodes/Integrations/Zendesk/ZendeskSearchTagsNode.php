<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ZendeskSearchTagsNode extends AbstractZendeskNode
{
    public function type(): string
    {
        return 'zendesk_search_tags';
    }

    public function name(): string
    {
        return 'Zendesk: Search Tags';
    }

    public function description(): string
    {
        return 'Finds tags that start with some text.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Starts with', 'At least two letters.', 'bill'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $tags = (array) ($this->zendesk($run, $config, 'GET', '/autocomplete/tags', ['name' => $this->requiredString($config, 'name', 'The text')])['tags'] ?? []);

        return ['tags' => $tags, 'count' => count($tags)];
    }
}
