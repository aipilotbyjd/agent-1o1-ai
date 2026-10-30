<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FirecrawlGetQueueStatusNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_get_queue_status';
    }

    public function name(): string
    {
        return 'Firecrawl: Get Queue Status';
    }

    public function description(): string
    {
        return 'Reads how many of the team\'s scrapes are waiting and running.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'GET', '/team/queue-status');

        return ['jobs_in_queue' => $answer['jobsInQueue'] ?? null, 'active' => $answer['activeJobsInQueue'] ?? null, 'waiting' => $answer['waitingJobsInQueue'] ?? null, 'max_concurrency' => $answer['maxConcurrency'] ?? null, 'most_recent_success' => $answer['mostRecentSuccess'] ?? null];
    }
}
