<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class AhrefsSiteAuditIssuesNode extends AbstractAhrefsReportNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'ahrefs_site_audit_issues';
    }

    public function name(): string
    {
        return 'Ahrefs: Site Audit Issues';
    }

    public function description(): string
    {
        return 'The issues found by a project\'s latest crawl, or by a past one. As a trigger it fires when an issue gets new URLs.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'date' => Field::text('Date', 'A timestamp in YYYY-MM-DDThh:mm:ss format specifying the crawl date to retrieve metrics from.'),
                'date_compared' => Field::advanced(Field::text('Compare with date', 'A timestamp in YYYY-MM-DDThh:mm:ss format specifying the crawl date to compare metrics with.')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/site-audit/issues',
            'items' => 'issues',
            'query' => ['project_id', 'date', 'date_compared'],
        ];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'issues';
    }

    /**
     * An issue is known by its id and how many URLs it is on.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['issue_id']) ? $item['issue_id'].':'.($item['crawled'] ?? '') : '';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Ahrefs: New site audit issue';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a Site Audit crawl finds an issue on URLs that did not have it.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 1440;
    }

    /**
     * Only issues that have appeared on more URLs.
     */
    public function triggerItems(array $output, array $config): array
    {
        return array_values(array_filter((array) ($output['issues'] ?? []), fn (mixed $issue): bool => is_array($issue) && ((int) ($issue['new'] ?? 0) + (int) ($issue['added'] ?? 0)) > 0));
    }
}
