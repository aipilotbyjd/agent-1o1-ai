<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogListHostTagsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_list_host_tags';
    }

    public function name(): string
    {
        return 'Datadog: List All Host Tags';
    }

    public function description(): string
    {
        return 'Lists every tag in use, with the hosts that have it.';
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
                'source' => Field::advanced(Field::text('Source', 'Only tags from this source, like users.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['tags' => (array) ($this->datadog($run, $config, 'GET', '/api/v1/tags/hosts', ['source' => $this->text($config, 'source')])['tags'] ?? [])];
    }
}
