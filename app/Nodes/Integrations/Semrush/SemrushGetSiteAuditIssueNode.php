<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushGetSiteAuditIssueNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_get_site_audit_issue';
    }

    public function name(): string
    {
        return 'Semrush: Get Site Audit Issue Pages';
    }

    public function description(): string
    {
        return 'The pages affected by one issue of an audit.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'snapshot_id', 'issue_id'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'snapshot_id' => $this->snapshotField(),
                'issue_id' => Field::integer('Issue id', 'From List Site Audit Issue Types.', null, 1),
                'page' => Field::advanced(Field::integer('Page', null, 1, 1)),
                'limit' => Field::advanced(Field::integer('Per page', null, 10, 1, 1000)),
                'sort' => Field::advanced(Field::text('Sort', null, 'DESC')),
                'filter' => Field::advanced(Field::text('Filter', 'As [+-]|field|operator|values, such as +|source_url|Co|blog.', '+|source_url|Co|blog')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $issue = (int) $this->requiredString($config, 'issue_id', 'The issue');
        $path = '/reports/v1/projects/'.$this->projectId($config).'/siteaudit/snapshot/'.$this->hexId($config, 'snapshot_id', 'The audit')."/issue/{$issue}";
        $answer = $this->semrushJson($run, $config, 'GET', $path, ['page' => $config['page'] ?? null, 'limit' => $config['limit'] ?? null, 'sort' => $config['sort'] ?? null, 'filter' => $config['filter'] ?? null]);

        return ['pages' => array_values((array) ($answer['data'] ?? [])), 'issue_id' => $answer['issue_id'] ?? $issue, 'total' => $answer['total'] ?? null, 'page' => $answer['page'] ?? null];
    }
}
