<?php

namespace App\Nodes\Integrations\Sentry;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * The poll keys on what can change, not on last seen, so a new event on an issue is not a change. Sentry
 * has no "recently changed" sort: an issue that changes without a recent event is outside the page read.
 */
class SentryListChangedIssuesNode extends AbstractSentryNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'sentry_list_changed_issues';
    }

    public function name(): string
    {
        return 'Sentry: List Changed Issues';
    }

    public function description(): string
    {
        return 'Lists recently active issues with their status, priority and assignee. As a trigger it starts the flow when one of those changes. Checked every few minutes.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $response = $this->sentryResponse($run, $config, 'GET', '/api/0/organizations/'.$this->org($run, $config).'/issues/', [...[
            'query' => $this->text($config, 'query') ?? '',
            'project' => filled($config['project'] ?? null) ? $this->projectFilter($run, $config) : null,
            'environment' => $this->text($config, 'environment'),
            'statsPeriod' => $this->text($config, 'stats_period'),
            'sort' => 'date',
            'limit' => $this->limit($config),
        ], 'cursor' => $this->text($config, 'cursor')]);
        $this->assertOk($response);
        $issues = array_values(array_filter((array) ($response->json() ?? []), 'is_array'));

        return ['issues' => array_map($this->shapeIssue(...), $issues), 'count' => count($issues), 'next_cursor' => self::nextCursor($response)];
    }

    public function triggerName(): string
    {
        return 'Sentry: Issue changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when the status, priority or assignee of a recently active Sentry issue changes. Checked every few minutes.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'issues';
    }

    public function triggerItemId(array $item): string
    {
        return implode(':', [$item['id'] ?? '', $item['status'] ?? '', $item['substatus'] ?? '', $item['priority'] ?? '', $item['assigned_to'] ?? '']);
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100, 'cursor' => null];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
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
