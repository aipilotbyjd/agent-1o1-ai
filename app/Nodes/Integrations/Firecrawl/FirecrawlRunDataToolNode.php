<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlRunDataToolNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_run_data_tool';
    }

    public function name(): string
    {
        return 'Firecrawl: Run Data Tool';
    }

    public function description(): string
    {
        return 'Runs a tool from Firecrawl\'s Alexandria catalogue of third-party data providers. Find one with Search, using the Alexandria source.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['provider', 'capability'],
            'properties' => [
                ...$this->credentialFields(),
                'provider' => Field::text('Provider'),
                'capability' => Field::text('Capability'),
                'options' => Field::json('Options', 'The tool\'s input, as its contract says.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'POST', '/scrape', ['alexandria' => $this->withoutEmpty([
            'provider' => $this->requiredString($config, 'provider', 'The provider'),
            'capability' => $this->requiredString($config, 'capability', 'The capability'),
            'options' => $this->decoded($config['options'] ?? null, 'The options'),
        ])], timeout: 75);

        return ['result' => data_get($answer, 'data.alexandria') ?? $answer['data'] ?? null];
    }
}
