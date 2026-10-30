<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlInteractNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_interact';
    }

    public function name(): string
    {
        return 'Firecrawl: Interact With Page';
    }

    public function description(): string
    {
        return 'Runs browser code on a page Firecrawl already scraped: click, fill forms, read what appears. Stop the session when done.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['scrape_id', 'code'],
            'properties' => [
                ...$this->credentialFields(),
                'scrape_id' => Field::text('Scrape id', 'The scrape_id of an earlier scrape.'),
                'code' => Field::textarea('Code', 'Playwright code, Python, or agent-browser commands.'),
                'language' => Field::select('Language', ['node', 'python', 'bash'], null, 'node'),
                'timeout' => Field::advanced(Field::integer('Timeout (seconds)', null, 30, 1, 90)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $timeout = $this->limit($config, 30, 90, 'timeout');
        $answer = $this->firecrawl($run, $config, 'POST', '/scrape/'.$this->pathId($config, 'scrape_id', 'The scrape id').'/interact', ['code' => $this->requiredString($config, 'code', 'The code'), 'language' => $this->textOf($config, 'language') ?? 'node', 'timeout' => $timeout], timeout: $timeout + 15);

        return ['stdout' => $answer['stdout'] ?? null, 'result' => $answer['result'] ?? null, 'stderr' => $answer['stderr'] ?? null, 'exit_code' => $answer['exitCode'] ?? null, 'live_view_url' => $answer['liveViewUrl'] ?? null];
    }
}
