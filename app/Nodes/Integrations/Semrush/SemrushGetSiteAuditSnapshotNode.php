<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SemrushGetSiteAuditSnapshotNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_get_site_audit_snapshot';
    }

    public function name(): string
    {
        return 'Semrush: Get Site Audit';
    }

    public function description(): string
    {
        return 'An overview of one finished audit. Costs 10,000 API units.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'snapshot_id'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'snapshot_id' => $this->snapshotField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->semrushJson($run, $config, 'GET', '/reports/v1/projects/'.$this->projectId($config).'/siteaudit/snapshot', ['snapshot_id' => $this->hexId($config, 'snapshot_id', 'The audit')]);
    }
}
