<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class AhrefsSiteAuditProjectsNode extends AbstractAhrefsReportNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'ahrefs_site_audit_projects';
    }

    public function name(): string
    {
        return 'Ahrefs: Site Audit Projects';
    }

    public function description(): string
    {
        return 'The Site Audit projects with their health scores. As a trigger it fires when a crawl finishes.';
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
                'date' => Field::text('Date', 'A timestamp in YYYY-MM-DDThh:mm:ss format specifying the crawl date to retrieve metrics from.'),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
                'project_name' => Field::advanced(Field::text('Project name', 'Filters projects by name.')),
                'project_url' => Field::advanced(Field::text('Project url', 'Filters projects by target URL (ignores protocol and trailing slash).')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/site-audit/projects',
            'items' => 'healthscores',
            'query' => ['date', 'project_id', 'project_name', 'project_url'],
        ];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'healthscores';
    }

    /**
     * A finished crawl is known by its project and date.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['project_id']) ? $item['project_id'].':'.($item['date'] ?? '') : '';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Ahrefs: Site audit crawl finished';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a Site Audit crawl of a project finishes.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 360;
    }

    /**
     * Only projects whose latest crawl has finished.
     */
    public function triggerItems(array $output, array $config): array
    {
        return array_values(array_filter((array) ($output['healthscores'] ?? []), fn (mixed $project): bool => is_array($project) && in_array($project['status'] ?? null, ['Completed', 'Stopped'], true)));
    }
}
