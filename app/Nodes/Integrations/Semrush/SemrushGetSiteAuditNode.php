<?php

namespace App\Nodes\Integrations\Semrush;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;

class SemrushGetSiteAuditNode extends AbstractSemrushNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'semrush_get_site_audit';
    }

    public function name(): string
    {
        return 'Semrush: Get Site Audit Overview';
    }

    public function description(): string
    {
        return 'A summary of the latest audit: errors, warnings, notices, checks and pages. As a trigger it fires when a new kind of issue is found.';
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
                'project_id' => $this->projectField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $info = $this->semrushJson($run, $config, 'GET', '/reports/v1/projects/'.$this->projectId($config).'/siteaudit/info');
        $issues = [];

        foreach ((array) ($info['defects'] ?? []) as $issue => $count) {
            $issues[] = ['id' => (string) $issue, 'issue_id' => (int) $issue, 'count' => $count];
        }

        return [...$info, 'issues' => $issues];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'issues';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Semrush: New site audit issue';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when an audit finds a kind of issue the project did not have before.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 1440;
    }
}
