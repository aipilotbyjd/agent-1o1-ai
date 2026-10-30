<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlSendFeedbackNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_send_feedback';
    }

    public function name(): string
    {
        return 'Firecrawl: Send Job Feedback';
    }

    public function description(): string
    {
        return 'Tells Firecrawl how well a search, scrape, parse or map job went.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['endpoint', 'job_id', 'rating'],
            'properties' => [
                ...$this->credentialFields(),
                'endpoint' => Field::select('Kind of job', ['search', 'scrape', 'parse', 'map']),
                'job_id' => Field::text('Job id'),
                'rating' => Field::select('Rating', ['good', 'partial', 'bad']),
                'note' => Field::textarea('Note'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'POST', '/feedback', $this->withoutEmpty(['endpoint' => $this->requiredString($config, 'endpoint', 'The kind of job'), 'jobId' => $this->requiredString($config, 'job_id', 'The job id'), 'rating' => $this->requiredString($config, 'rating', 'The rating'), 'note' => $this->textOf($config, 'note')]));

        return ['feedback_id' => $answer['feedbackId'] ?? null, 'credits_refunded' => $answer['creditsRefunded'] ?? null];
    }
}
