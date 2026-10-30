<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushGetSiteAuditPageNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_get_site_audit_page';
    }

    public function name(): string
    {
        return 'Semrush: Get Site Audit Page';
    }

    public function description(): string
    {
        return 'Reads what an audit found on one page.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'page_id'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'page_id' => Field::text('Page id', 'From Find Site Audit Page.', '54102d9e0cf2e0c100696c88'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->semrushJson($run, $config, 'GET', '/reports/v1/projects/'.$this->projectId($config).'/siteaudit/page/'.$this->hexId($config, 'page_id', 'The page'));
    }
}
