<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class FirecrawlListMonitorChangesNode extends AbstractFirecrawlNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'firecrawl_list_monitor_changes';
    }

    public function name(): string
    {
        return 'Firecrawl: List Monitor Page Changes';
    }

    public function description(): string
    {
        return 'Lists the pages that changed, appeared or went away in a monitor\'s latest checks. As a trigger it starts the flow once per page.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['monitor_id'],
            'properties' => [
                ...$this->credentialFields(),
                'monitor_id' => $this->monitorField(),
                'page_status' => Field::select('Kind of change', ['changed', 'new', 'removed', 'error'], null, 'changed'),
                'checks' => Field::advanced(Field::integer('Checks to read', null, 3, 1, 5)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $monitor = $this->pathId($config, 'monitor_id', 'The monitor');
        $status = $this->textOf($config, 'page_status') ?? 'changed';
        $pages = [];
        $checks = array_filter((array) ($this->firecrawl($run, $config, 'GET', "/monitor/{$monitor}/checks", query: ['limit' => 20])['data'] ?? []), fn (mixed $check): bool => is_array($check) && $this->checkIsDone($check) && filled($check['id'] ?? null));

        foreach (array_slice(array_values($checks), 0, $this->limit($config, 3, 5, 'checks')) as $check) {
            $detail = $this->firecrawl($run, $config, 'GET', "/monitor/{$monitor}/checks/".$this->pathId($check, 'id', 'The check id'), query: ['status' => $status, 'limit' => 100]);

            foreach ((array) data_get($detail, 'data.pages', []) as $page) {
                if (is_array($page) && filled($page['url'] ?? null)) {
                    $pages[] = ['id' => $check['id'].':'.($page['id'] ?? sha1((string) $page['url'])), 'check_id' => $check['id'], 'url' => $page['url'], 'status' => $page['status'] ?? $status, 'previous_scrape_id' => $page['previousScrapeId'] ?? null, 'current_scrape_id' => $page['currentScrapeId'] ?? null, 'status_code' => $page['statusCode'] ?? null, 'error' => $page['error'] ?? null];
                }
            }
        }

        return ['pages' => $pages, 'count' => count($pages)];
    }

    public function triggerName(): string
    {
        return 'Firecrawl: Monitor page changed';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow for each page a Firecrawl monitor found changed, new or gone. Instant where the monitor\'s webhook is free, otherwise checked every few minutes.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'pages';
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
