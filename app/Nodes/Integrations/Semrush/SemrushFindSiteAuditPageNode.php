<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushFindSiteAuditPageNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_find_site_audit_page';
    }

    public function name(): string
    {
        return 'Semrush: Find Site Audit Page';
    }

    public function description(): string
    {
        return 'Finds a crawled page\'s id by its URL.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'url'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'url' => Field::text('URL holds', null, 'example.com/blog'),
                'limit' => Field::advanced(Field::integer('Limit', null, 10, 1, 1000)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->semrushJson($run, $config, 'GET', '/reports/v1/projects/'.$this->projectId($config).'/siteaudit/page/list', ['url' => $this->requiredString($config, 'url', 'The URL'), 'limit' => $config['limit'] ?? null]);

        return ['pages' => array_values((array) ($answer['data'] ?? [])), 'total' => $answer['total'] ?? null];
    }
}
