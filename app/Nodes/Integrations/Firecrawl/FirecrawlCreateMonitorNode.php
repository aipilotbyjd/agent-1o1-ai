<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class FirecrawlCreateMonitorNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_create_monitor';
    }

    public function name(): string
    {
        return 'Firecrawl: Create Monitor';
    }

    public function description(): string
    {
        return 'Checks pages on a schedule and records what changed. Start a flow from the changes with the Monitor Check Completed or Monitor Page Changed trigger.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name'),
                ...$this->monitorFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->requiredString($config, 'name', 'The name');
        $body = $this->monitorBody($config);

        if (($body['schedule'] ?? []) === [] || ($body['targets'] ?? []) === []) {
            throw new InvalidArgumentException('A monitor needs a schedule and at least one page to watch.');
        }

        return ['monitor' => $this->firecrawl($run, $config, 'POST', '/monitor', $body)['data'] ?? []];
    }
}
