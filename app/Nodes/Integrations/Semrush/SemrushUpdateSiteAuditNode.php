<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushUpdateSiteAuditNode extends AbstractSemrushNode
{
    public function type(): string
    {
        return 'semrush_update_site_audit';
    }

    public function name(): string
    {
        return 'Semrush: Edit Site Audit';
    }

    public function description(): string
    {
        return 'Changes a project\'s Site Audit: what to crawl, how much and when.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id', 'domain', 'page_limit'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => $this->projectField(),
                'domain' => Field::text('Domain', 'The project URL.', 'www.example.com'),
                'page_limit' => Field::integer('Pages to crawl', null, 100, 1, 100000),
                'schedule_day' => Field::advanced(Field::integer('Run on day', 'From 1 to 7 for a weekly run. 0 starts it by hand.', 0, 0, 7)),
                'notify' => Field::advanced(Field::boolean('Email when finished', null, false)),
                'allow' => Field::advanced(Field::list('Allow masks')),
                'disallow' => Field::advanced(Field::list('Disallow masks')),
                'user_agent_type' => Field::advanced(Field::select('Crawl as', ['7' => 'SiteAuditBot Desktop', '8' => 'SiteAuditBot Mobile', '2' => 'GoogleBot Desktop', '3' => 'GoogleBot Mobile', '9' => 'OpenAI-Search'])),
                'removed_parameters' => Field::advanced(Field::list('Ignore URL parameters')),
                'crawl_subdomains' => Field::advanced(Field::boolean('Crawl subdomains', null, false)),
                'respect_crawl_delay' => Field::advanced(Field::boolean('Follow Crawl-delay', null, false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->semrushJson($run, $config, 'POST', '/management/v1/projects/'.$this->projectId($config).'/siteaudit/save', [], $this->auditBody($config));

        return ['updated' => true];
    }

    /**
     * The audit settings as Semrush names them.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function auditBody(array $config): array
    {
        return array_filter([
            'domain' => self::bareDomain($this->requiredString($config, 'domain', 'The domain')),
            'pageLimit' => (int) $this->requiredString($config, 'page_limit', 'The pages to crawl'),
            'scheduleDay' => is_numeric($config['schedule_day'] ?? null) ? (int) $config['schedule_day'] : null,
            'notify' => $config['notify'] ?? null,
            'allow' => $this->listOf($config, 'allow') ?: null,
            'disallow' => $this->listOf($config, 'disallow') ?: null,
            'userAgentType' => is_numeric($config['user_agent_type'] ?? null) ? (int) $config['user_agent_type'] : null,
            'removedParameters' => $this->listOf($config, 'removed_parameters') ?: null,
            'crawlSubdomains' => $config['crawl_subdomains'] ?? null,
            'respectCrawlDelay' => $config['respect_crawl_delay'] ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
