<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogGetHostTagsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_get_host_tags';
    }

    public function name(): string
    {
        return 'Datadog: Get Host Tags';
    }

    public function description(): string
    {
        return 'Lists the tags of a host.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['host'],
            'properties' => [
                ...$this->credentialFields(),
                'host' => Field::text('Host', null, 'web-1'),
                'source' => Field::advanced(Field::text('Source')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $host = $this->pathId($config, 'host', 'The host');

        return ['host' => rawurldecode($host), 'tags' => array_values((array) ($this->datadog($run, $config, 'GET', "/api/v1/tags/hosts/{$host}", ['source' => $this->text($config, 'source')])['tags'] ?? []))];
    }
}
