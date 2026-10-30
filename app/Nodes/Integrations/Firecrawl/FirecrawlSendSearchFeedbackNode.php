<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlSendSearchFeedbackNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_send_search_feedback';
    }

    public function name(): string
    {
        return 'Firecrawl: Rate Search Results';
    }

    public function description(): string
    {
        return 'Tells Firecrawl how good a search\'s results were.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['search_id', 'rating'],
            'properties' => [
                ...$this->credentialFields(),
                'search_id' => Field::text('Search id', 'The id Search returned.'),
                'rating' => Field::select('Rating', ['good', 'partial', 'bad']),
                'suggestions' => Field::advanced(Field::textarea('Suggestions')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'POST', '/search/'.$this->pathId($config, 'search_id', 'The search id').'/feedback', $this->withoutEmpty(['rating' => $this->requiredString($config, 'rating', 'The rating'), 'querySuggestions' => $this->textOf($config, 'suggestions')]));

        return ['feedback_id' => $answer['feedbackId'] ?? null, 'credits_refunded' => $answer['creditsRefunded'] ?? null];
    }
}
