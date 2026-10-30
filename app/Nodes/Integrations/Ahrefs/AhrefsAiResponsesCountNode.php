<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsAiResponsesCountNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_ai_responses_count';
    }

    public function name(): string
    {
        return 'Ahrefs: AI Responses Count';
    }

    public function description(): string
    {
        return 'How many AI chatbot responses cite a domain or URL, by platform.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['target'],
            'properties' => [
                ...$this->credentialFields(),
                'target' => Field::text('Target', 'The target of the search: a domain or a URL.'),
                'date' => Field::text('Date', 'The date to report on in YYYY-MM-DD format.'),
                'country' => Field::text('Country', 'A two-letter country code (ISO 3166-1 alpha-2).'),
                'select' => Field::advanced(Field::text('Columns', 'Separated by commas. Ahrefs charges by the column. Empty uses the usual ones.', 'all_platforms,chatgpt,copilot,gemini,google_ai_mode,google_ai_overviews,google_ai_overviews_keywords,grok,perplexity')),
                'mode' => Field::advanced(Field::select('Scope', ['exact', 'prefix', 'domain', 'subdomains'], 'The scope of the target.', 'subdomains')),
                'protocol' => Field::advanced(Field::select('Protocol', ['both', 'http', 'https'], 'The protocol of your target.', 'both')),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/site-explorer/ai-responses-count',
            'items' => 'ai_responses_count',
            'select' => 'all_platforms,chatgpt,copilot,gemini,google_ai_mode,google_ai_overviews,google_ai_overviews_keywords,grok,perplexity',
            'query' => ['target', 'date', 'country', 'select', 'mode', 'protocol'],
        ];
    }
}
