<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushGetSiteAuditHistoryNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_get_site_audit_history';
    }

    public function name(): string
    {
        return 'Semrush: Get Site Audit History';
    }

    public function description(): string
    {
        return 'Audit results over time. Costs 10,000 API units for each audit returned.';
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
                'limit' => Field::integer('Limit', 'Each audit returned costs 10,000 API units.', 7, 1, 100),
                'offset' => Field::advanced(Field::integer('Skip', null, 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->semrushJson($run, $config, 'GET', '/reports/v1/projects/'.$this->projectId($config).'/siteaudit/history', ['limit' => (int) ($config['limit'] ?? 7), 'offset' => (int) ($config['offset'] ?? 0)]);
        $audits = array_is_list($answer) ? $answer : (array) ($answer['data'] ?? $answer);

        return ['audits' => array_values($audits), 'count' => count($audits)];
    }
}
