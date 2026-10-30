<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryListIssuesNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_issues';
    }

    public function name(): string
    {
        return 'Sentry: List Issues';
    }

    public function description(): string
    {
        return 'Lists and searches the issues of an organization, optionally of one project.';
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
                'project' => $this->projectField(),
                'query' => Field::text('Search', 'Sentry search syntax.', 'is:unresolved level:error'),
                'environment' => Field::text('Environment', null, 'production'),
                'stats_period' => Field::advanced(Field::text('Period', 'Like 24h or 14d. Default 14d.')),
                ...$this->pageFields(),
                'sort' => Field::advanced(Field::select('Order', ['date' => 'Last seen', 'new' => 'First seen', 'freq' => 'Events', 'user' => 'Users', 'trends' => 'Trending', 'inbox' => 'Date added'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->sentryResponse($run, $config, 'GET', '/api/0/organizations/'.$this->org($run, $config).'/issues/', [...[
            'query' => $this->text($config, 'query') ?? 'is:unresolved',
            'project' => filled($config['project'] ?? null) ? $this->projectFilter($run, $config) : null,
            'environment' => $this->text($config, 'environment'),
            'statsPeriod' => $this->text($config, 'stats_period'),
            'sort' => $this->text($config, 'sort'),
            'limit' => $this->limit($config),
        ], 'cursor' => $this->text($config, 'cursor')]);
        $this->assertOk($response);
        $issues = array_values(array_filter((array) ($response->json() ?? []), 'is_array'));

        return ['issues' => array_map($this->shapeIssue(...), $issues), 'count' => count($issues), 'next_cursor' => self::nextCursor($response)];
    }

    /**
     * Issue lists filter by project id, so a slug is looked up first.
     *
     * @param  array<string, mixed>  $config
     */
    private function projectFilter(Run $run, array $config): string
    {
        $project = $this->project($config);

        return ctype_digit($project) ? $project : (string) ($this->sentry($run, $config, 'GET', '/api/0/projects/'.$this->org($run, $config)."/{$project}/")['id'] ?? $project);
    }
}
