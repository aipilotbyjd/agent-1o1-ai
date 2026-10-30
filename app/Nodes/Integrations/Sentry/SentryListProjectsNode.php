<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryListProjectsNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_projects';
    }

    public function name(): string
    {
        return 'Sentry: List Projects';
    }

    public function description(): string
    {
        return 'Lists the projects of an organization.';
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
                'query' => Field::text('Search'),
                ...$this->pageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/projects/', 'projects', ['query' => $this->text($config, 'query'), 'per_page' => $this->limit($config)], $this->shapeProject(...));
    }
}
