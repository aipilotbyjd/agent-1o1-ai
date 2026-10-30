<?php

namespace App\Nodes\Integrations\Semrush;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;

class SemrushListSiteAuditSnapshotsNode extends AbstractSemrushNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'semrush_list_site_audit_snapshots';
    }

    public function name(): string
    {
        return 'Semrush: List Site Audits';
    }

    public function description(): string
    {
        return 'Lists a project\'s finished audits, newest last as Semrush gives them. As a trigger it fires when an audit finishes.';
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
        $snapshots = array_values(array_filter((array) ($this->semrushJson($run, $config, 'GET', '/reports/v1/projects/'.$this->projectId($config).'/siteaudit/snapshots')['snapshots'] ?? []), 'is_array'));

        return ['snapshots' => array_map(fn (array $snapshot): array => [...$snapshot, 'id' => $snapshot['snapshot_id'] ?? null, 'finished_at' => is_numeric($snapshot['finish_date'] ?? null) ? gmdate('c', intdiv((int) $snapshot['finish_date'], 1000)) : null], $snapshots), 'count' => count($snapshots)];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'snapshots';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Semrush: Completed site audit';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when an audit of the project finishes.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 60;
    }
}
