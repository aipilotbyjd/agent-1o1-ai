<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SentryQueryEventsNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_query_events';
    }

    public function name(): string
    {
        return 'Sentry: Query Events';
    }

    public function description(): string
    {
        return 'Queries errors, spans, logs or metrics across an organization in table form.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['fields'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'dataset' => Field::select('Dataset', ['errors' => 'Errors', 'spans' => 'Spans', 'logs' => 'Logs', 'tracemetrics' => 'Metrics', 'profile_functions' => 'Profile functions', 'uptime_results' => 'Uptime results'], null, 'errors'),
                'fields' => Field::list('Fields', 'Columns or functions, like title and count().'),
                'query' => Field::text('Search', 'Sentry search syntax.', 'is:unresolved level:error'),
                'stats_period' => Field::text('Period', 'Default 24h.', '24h'),
                'sort' => Field::text('Order by', 'A field; start with - for descending.', '-count()'),
                'environment' => Field::advanced(Field::text('Environment')),
                'project_ids' => Field::advanced(Field::list('Project ids', 'Numeric ids.')),
                ...$this->pageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $fields = $this->items($config, 'fields') ?: throw new InvalidArgumentException('At least one field is required.');
        $response = $this->sentryResponse($run, $config, 'GET', '/api/0/organizations/'.$this->org($run, $config).'/events/', [
            'field' => $fields,
            'dataset' => $this->text($config, 'dataset') ?? 'errors',
            'query' => $this->text($config, 'query'),
            'project' => $this->items($config, 'project_ids'),
            'environment' => $this->text($config, 'environment'),
            'statsPeriod' => $this->text($config, 'stats_period') ?? '24h',
            'sort' => $this->text($config, 'sort'),
            'per_page' => $this->limit($config),
            'cursor' => $this->text($config, 'cursor'),
        ]);
        $this->assertOk($response);
        $rows = array_values((array) ($response->json('data') ?? []));

        return ['rows' => $rows, 'count' => count($rows), 'next_cursor' => self::nextCursor($response), 'meta' => $response->json('meta')];
    }
}
