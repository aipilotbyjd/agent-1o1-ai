<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogDeleteHostTagsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_delete_host_tags';
    }

    public function name(): string
    {
        return 'Datadog: Remove Host Tags';
    }

    public function description(): string
    {
        return 'Removes all the tags of a host for a source.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['host'],
            'properties' => [
                ...$this->credentialFields(),
                'host' => Field::text('Host', null, 'web-1'),
                'source' => Field::advanced(Field::text('Source', 'Default users.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $host = $this->pathId($config, 'host', 'The host');
        $this->datadog($run, $config, 'DELETE', "/api/v1/tags/hosts/{$host}", ['source' => $this->text($config, 'source')]);

        return ['deleted' => true, 'host' => rawurldecode($host)];
    }
}
