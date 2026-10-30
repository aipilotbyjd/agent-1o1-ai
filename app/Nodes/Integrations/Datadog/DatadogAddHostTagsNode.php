<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogAddHostTagsNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_add_host_tags';
    }

    public function name(): string
    {
        return 'Datadog: Add Host Tags';
    }

    public function description(): string
    {
        return 'Adds tags to a host, keeping the ones it has.';
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
        $tags = $this->listOf($config, 'tags') ?: throw new InvalidArgumentException('At least one tag is required.');

        return $this->datadog($run, $config, 'POST', "/api/v1/tags/hosts/{$host}", ['source' => $this->text($config, 'source')], ['tags' => $tags]);
    }
}
