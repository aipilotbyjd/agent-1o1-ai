<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryListTagsNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_tags';
    }

    public function name(): string
    {
        return 'Sentry: List Tags';
    }

    public function description(): string
    {
        return 'Lists the tag keys of an organization\'s events.';
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
                'organization' => $this->organizationField(),
                'stats_period' => Field::text('Period', 'Like 24h or 14d.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/tags/', 'tags', ['statsPeriod' => $this->text($config, 'stats_period')]);
    }
}
