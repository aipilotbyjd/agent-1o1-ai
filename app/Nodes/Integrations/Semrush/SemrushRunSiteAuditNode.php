<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SemrushRunSiteAuditNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_run_site_audit';
    }

    public function name(): string
    {
        return 'Semrush: Run Site Audit';
    }

    public function description(): string
    {
        return 'Starts a new audit of a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
        return $this->semrushJson($run, $config, 'POST', '/reports/v1/projects/'.$this->projectId($config).'/siteaudit/launch');
    }
}
