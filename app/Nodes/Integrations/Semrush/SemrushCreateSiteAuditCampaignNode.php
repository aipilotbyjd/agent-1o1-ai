<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use RuntimeException;

class SemrushCreateSiteAuditCampaignNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_create_site_audit_campaign';
    }

    public function name(): string
    {
        return 'Semrush: Create Site Audit Campaign';
    }

    public function description(): string
    {
        return 'Creates a project and sets up a Site Audit for it in one step.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'start_crawl_url', 'pages_limit'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Project name', null, 'My site'),
                'start_crawl_url' => Field::text('Domain to crawl', null, 'www.example.com'),
                'pages_limit' => Field::integer('Pages to crawl', null, 100, 1, 100000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $domain = $this->domain($config, 'start_crawl_url', 'The domain to crawl');
        $project = $this->semrushJson($run, $config, 'POST', '/management/v1/projects', [], ['url' => $domain, 'project_name' => $this->requiredString($config, 'name', 'The project name')]);
        $id = $project['project_id'] ?? throw new RuntimeException('Semrush did not return the new project.');

        $this->semrushJson($run, $config, 'POST', "/management/v1/projects/{$id}/siteaudit/enable", [], ['domain' => $domain, 'pageLimit' => (int) $this->requiredString($config, 'pages_limit', 'The pages to crawl')]);

        return ['project_id' => $id, 'project_name' => $project['project_name'] ?? null, 'url' => $project['url'] ?? $domain, 'site_audit_enabled' => true];
    }
}
