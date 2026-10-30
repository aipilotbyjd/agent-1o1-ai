<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogUpdateHostTagsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_update_host_tags';
    }

    public function name(): string
    {
        return 'Datadog: Replace Host Tags';
    }

    public function description(): string
    {
        return 'Replaces the tags of a host.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['host', 'tags'],
            'properties' => [
                ...$this->credentialFields(),
                'host' => Field::text('Host', null, 'web-1'),
                'tags' => Field::list('Tags', 'Like env:prod.'),
                'source' => Field::advanced(Field::text('Source', 'Default users.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $host = $this->pathId($config, 'host', 'The host');

        return $this->datadog($run, $config, 'PUT', "/api/v1/tags/hosts/{$host}", ['source' => $this->text($config, 'source')], ['tags' => $this->listOf($config, 'tags')]);
    }
}
