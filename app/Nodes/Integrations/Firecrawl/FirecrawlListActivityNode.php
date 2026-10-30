<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class FirecrawlListActivityNode extends AbstractFirecrawlNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'firecrawl_list_activity';
    }

    public function name(): string
    {
        return 'Firecrawl: List Recent Activity';
    }

    public function description(): string
    {
        return 'Lists the team\'s recent API jobs, newest first, optionally of one kind. As a trigger it starts the flow when a job of that kind starts, such as a crawl. Checked every few minutes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'endpoint' => Field::select('Kind of job', ['crawl', 'batch_scrape', 'extract', 'agent', 'scrape', 'search', 'map', 'interact'], 'Leave empty for all.'),
                'limit' => Field::integer('Max results', null, 25, 1, 100),
                'cursor' => Field::advanced(Field::text('Page cursor', 'The next_cursor of the last list.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'GET', '/team/activity', query: ['endpoint' => $this->textOf($config, 'endpoint'), 'limit' => $this->limit($config, 25, 100), 'cursor' => $this->textOf($config, 'cursor')]);
        $jobs = array_values(array_filter((array) ($answer['data'] ?? []), 'is_array'));

        return ['jobs' => array_map(fn (array $job): array => ['id' => $job['id'] ?? null, 'kind' => $job['endpoint'] ?? null, 'target' => $job['target'] ?? null, 'created_at' => $job['created_at'] ?? null], $jobs), 'count' => count($jobs), 'next_cursor' => $answer['cursor'] ?? null];
    }

    public function triggerName(): string
    {
        return 'Firecrawl: Job started';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a Firecrawl job of the chosen kind starts, such as a crawl. Checked every few minutes.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'jobs';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
