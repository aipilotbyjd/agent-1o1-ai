<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryListReplaysNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_replays';
    }

    public function name(): string
    {
        return 'Sentry: List Replays';
    }

    public function description(): string
    {
        return 'Lists the session replays of an organization.';
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
                'stats_period' => Field::text('Period', 'Default 14d.'),
                'environment' => Field::text('Environment'),
                ...$this->pageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->sentryResponse($run, $config, 'GET', '/api/0/organizations/'.$this->org($run, $config).'/replays/', ['query' => $this->text($config, 'query'), 'statsPeriod' => $this->text($config, 'stats_period') ?? '14d', 'environment' => $this->text($config, 'environment'), 'per_page' => $this->limit($config), 'cursor' => $this->text($config, 'cursor')]);
        $this->assertOk($response);
        $replays = array_values((array) ($response->json('data') ?? []));

        return ['replays' => $replays, 'count' => count($replays), 'next_cursor' => self::nextCursor($response)];
    }
}
