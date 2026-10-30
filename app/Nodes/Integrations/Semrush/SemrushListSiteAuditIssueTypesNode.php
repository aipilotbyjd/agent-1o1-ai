<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SemrushListSiteAuditIssueTypesNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_list_site_audit_issue_types';
    }

    public function name(): string
    {
        return 'Semrush: List Site Audit Issue Types';
    }

    public function description(): string
    {
        return 'What each issue id means: its title and description.';
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
        $issues = array_values((array) ($this->semrushJson($run, $config, 'GET', '/reports/v1/projects/'.$this->projectId($config).'/siteaudit/meta/issues')['issues'] ?? []));

        return ['issues' => $issues, 'count' => count($issues)];
    }
}
