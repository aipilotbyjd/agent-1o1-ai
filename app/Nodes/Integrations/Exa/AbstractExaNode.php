<?php

namespace App\Nodes\Integrations\Exa;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ReadsApiSettings;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the Exa node
 * family (the key in `x-api-key`). Websets live under their own root, the rest under
 * the main one. Agent runs and batches are jobs: a Create node returns an id, and a
 * Get node reads the result, so no step holds a worker while Exa researches.
 */
abstract class AbstractExaNode implements DeclaresEffect, NodeContract
{
    use ReadsApiSettings;
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.exa.ai';

    protected const string WEBSETS = '/websets/v0';

    /**
     * Exa's Batch API is in beta and asks for this header on every call.
     */
    protected const string BATCH_BETA = 'batches-2026-06-06';

    public function category(): string
    {
        return 'exa';
    }

    /**
     * Call Exa and return the decoded body (an empty array for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<int|string, mixed>|null  $body
     * @param  array<string, mixed>  $query  null values are dropped
     * @param  array<string, string>  $headers
     * @return array<int|string, mixed>
     *
     * @throws RuntimeException
     */
    protected function exa(Run $run, array $config, string $method, string $path, ?array $body = null, array $query = [], int $timeout = 30, array $headers = []): array
    {
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $body;
        }

        $response = Http::withHeaders(['x-api-key' => $this->resolveAccessToken($run, $config), ...$headers])->acceptJson()->timeout($timeout)->send(strtoupper($method), self::API_URL.$path, $options);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = (string) ($response->json('error') ?? $response->json('message') ?? "HTTP {$response->status()}");
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Exa: the API key was refused.',
            $response->status() === 402 => ' The Exa team is out of credits.',
            $response->status() === 403 => ' The Exa key or plan does not include this.',
            $response->status() === 429 => ' Exa is rate limiting the team; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Exa error: '.mb_substr($message, 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * What to read from each page.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function contentFields(): array
    {
        return [
            'text' => Field::boolean('Page text', 'Returns each page\'s text.', true),
            'max_characters' => Field::advanced(Field::integer('Max characters of text', null, null, 1)),
            'highlights' => Field::advanced(Field::boolean('Highlights', 'Returns the most relevant passages.')),
            'highlights_query' => Field::advanced(Field::text('Highlight what', 'Picks passages about this.')),
            'summary' => Field::advanced(Field::boolean('Summary')),
            'summary_query' => Field::advanced(Field::text('Summary about', 'Focuses each summary on this.')),
            'max_age_hours' => Field::advanced(Field::integer('Accept a cached copy up to (hours)', 'Use 0 to always read the page fresh.', null, 0)),
            'subpages' => Field::advanced(Field::integer('Subpages per page', null, null, 0)),
            'subpage_target' => Field::advanced(Field::text('Subpages about', null, 'pricing')),
            'links' => Field::advanced(Field::integer('Links per page', null, null, 0)),
            'image_links' => Field::advanced(Field::integer('Image links per page', null, null, 0)),
        ];
    }

    /**
     * What to read from each page, as Exa names it.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function contentOptions(array $config): array
    {
        $maxCharacters = $this->intOf($config, 'max_characters');
        $highlightsQuery = $this->textOf($config, 'highlights_query');
        $summaryQuery = $this->textOf($config, 'summary_query');

        return $this->withoutEmpty([
            'text' => $maxCharacters !== null ? ['maxCharacters' => $maxCharacters] : $this->boolOf($config, 'text'),
            'highlights' => $highlightsQuery !== null ? ['query' => $highlightsQuery] : ($this->boolOf($config, 'highlights') ?: null),
            'summary' => $summaryQuery !== null ? ['query' => $summaryQuery] : ($this->boolOf($config, 'summary') ?: null),
            'maxAgeHours' => $this->intOf($config, 'max_age_hours'),
            'subpages' => $this->intOf($config, 'subpages'),
            'subpageTarget' => $this->textOf($config, 'subpage_target'),
            'extras' => $this->withoutEmpty(['links' => $this->intOf($config, 'links'), 'imageLinks' => $this->intOf($config, 'image_links')]),
        ]);
    }

    /**
     * The filters a search and a "find similar" share.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function filterFields(): array
    {
        return [
            'num_results' => Field::integer('Max results', null, 10, 1, 100),
            'category' => Field::select('Category', ['company', 'publication', 'news', 'personal site', 'financial report', 'people'], 'Leave empty for all of the web.'),
            'include_domains' => Field::advanced(Field::list('Only these sites', null, 'example.com')),
            'exclude_domains' => Field::advanced(Field::list('Not these sites')),
            'start_published_date' => Field::advanced(Field::datetime('Published after')),
            'end_published_date' => Field::advanced(Field::datetime('Published before')),
            'start_crawl_date' => Field::advanced(Field::datetime('Found after')),
            'end_crawl_date' => Field::advanced(Field::datetime('Found before')),
            'include_text' => Field::advanced(Field::text('Must contain', 'A phrase of up to 5 words.')),
            'exclude_text' => Field::advanced(Field::text('Must not contain', 'A phrase of up to 5 words.')),
        ];
    }

    /**
     * The filters, as Exa names them.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function filterOptions(array $config): array
    {
        return [
            'numResults' => $this->intOf($config, 'num_results'),
            'category' => $this->textOf($config, 'category'),
            'includeDomains' => $this->stringList($config['include_domains'] ?? []),
            'excludeDomains' => $this->stringList($config['exclude_domains'] ?? []),
            'startPublishedDate' => $this->textOf($config, 'start_published_date'),
            'endPublishedDate' => $this->textOf($config, 'end_published_date'),
            'startCrawlDate' => $this->textOf($config, 'start_crawl_date'),
            'endCrawlDate' => $this->textOf($config, 'end_crawl_date'),
            'includeText' => array_filter([$this->textOf($config, 'include_text')]),
            'excludeText' => array_filter([$this->textOf($config, 'exclude_text')]),
        ];
    }

    /**
     * A search answer.
     *
     * @param  array<int|string, mixed>  $answer
     * @return array<string, mixed>
     */
    protected function shapeResults(array $answer): array
    {
        $results = array_values(array_filter((array) ($answer['results'] ?? []), 'is_array'));

        return $this->withoutEmpty([
            'results' => array_map($this->shapeResult(...), $results),
            'count' => count($results),
            'output' => $answer['output'] ?? null,
            'context' => $answer['context'] ?? null,
            'statuses' => $answer['statuses'] ?? null,
            'cost_usd' => data_get($answer, 'costDollars.total'),
            'request_id' => $answer['requestId'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function shapeResult(array $result): array
    {
        return $this->withoutEmpty([
            'id' => $result['id'] ?? null,
            'url' => $result['url'] ?? null,
            'title' => $result['title'] ?? null,
            'author' => $result['author'] ?? null,
            'published_date' => $result['publishedDate'] ?? null,
            'score' => $result['score'] ?? null,
            'text' => $result['text'] ?? null,
            'highlights' => $result['highlights'] ?? null,
            'summary' => $result['summary'] ?? null,
            'image' => $result['image'] ?? null,
            'favicon' => $result['favicon'] ?? null,
            'subpages' => $result['subpages'] ?? null,
            'extras' => $result['extras'] ?? null,
        ]);
    }

    /**
     * An agent run.
     *
     * @param  array<int|string, mixed>  $run
     * @return array<string, mixed>
     */
    protected function shapeAgentRun(array $run): array
    {
        return [
            'id' => $run['id'] ?? null,
            'status' => $run['status'] ?? null,
            'finished' => in_array($run['status'] ?? null, ['completed', 'failed', 'cancelled'], true),
            'stop_reason' => $run['stopReason'] ?? null,
            'query' => data_get($run, 'request.query'),
            'text' => data_get($run, 'output.text'),
            'structured' => data_get($run, 'output.structured'),
            'created_at' => $run['createdAt'] ?? null,
            'completed_at' => $run['completedAt'] ?? null,
        ];
    }

    /**
     * A page of a list that goes by cursor.
     *
     * @param  array<int|string, mixed>  $page
     * @return array{items: list<array<string, mixed>>, count: int, next_cursor: mixed}
     */
    protected function shapeList(array $page): array
    {
        $items = array_values(array_filter((array) ($page['data'] ?? []), 'is_array'));

        return ['items' => $items, 'count' => count($items), 'next_cursor' => $page['nextCursor'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function pageQuery(array $config, int $default = 25, int $max = 100): array
    {
        return ['limit' => $this->limit($config, $default, $max), 'cursor' => $this->textOf($config, 'cursor')];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function pageFields(int $default = 25, int $max = 100): array
    {
        return [
            'limit' => Field::integer('Max results', null, $default, 1, $max),
            'cursor' => Field::advanced(Field::text('Page cursor', 'The next_cursor of the last list.')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function websetField(): array
    {
        return Field::dynamic('Webset', 'exa.websets', null, 'Pick a webset.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function monitorField(): array
    {
        return Field::dynamic('Monitor', 'exa.monitors', null, 'Pick a monitor.');
    }

    /**
     * What a monitor's search looks for, and when.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function monitorFields(): array
    {
        return [
            'name' => Field::text('Name'),
            'query' => Field::textarea('Search for'),
            'num_results' => Field::integer('Max results per run', null, 10, 1, 100),
            'period' => Field::text('Every', 'How often it runs, as Exa writes it: 1d, 1w...', '1d'),
            'webhook_url' => Field::text('Send results to', 'Exa posts each run here: a Webhook trigger\'s URL, for instance.', 'https://'),
            'webhook_events' => Field::advanced(Field::list('Events to send', 'monitor.run.completed, monitor.run.created, monitor.created, monitor.updated, monitor.deleted.', 'monitor.run.completed')),
            'include_domains' => Field::advanced(Field::list('Only these sites')),
            'exclude_domains' => Field::advanced(Field::list('Not these sites')),
            'metadata' => Field::advanced(Field::keyValue('Metadata')),
        ];
    }

    /**
     * A monitor, as Exa names it.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function monitorBody(array $config): array
    {
        $period = $this->textOf($config, 'period');
        $webhook = $this->textOf($config, 'webhook_url');

        return $this->withoutEmpty([
            'name' => $this->textOf($config, 'name'),
            'search' => $this->withoutEmpty([
                'query' => $this->textOf($config, 'query'),
                'numResults' => $this->intOf($config, 'num_results'),
                'includeDomains' => $this->stringList($config['include_domains'] ?? []),
                'excludeDomains' => $this->stringList($config['exclude_domains'] ?? []),
            ]),
            'trigger' => $period === null ? null : ['type' => 'interval', 'period' => $period],
            'webhook' => $webhook === null ? null : $this->withoutEmpty(['url' => $this->checkedUrl($webhook), 'events' => $this->stringList($config['webhook_events'] ?? [])]),
            'metadata' => $this->decoded($config['metadata'] ?? null, 'The metadata'),
        ]);
    }

    /**
     * What a webset's search looks for.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function websetSearchFields(): array
    {
        return [
            'query' => Field::textarea('Find', 'What the webset should hold, in words.', 'AI startups in Berlin that raised a seed round'),
            'count' => Field::integer('How many', null, 10, 1),
            'entity' => Field::advanced(Field::json('Kind of result', 'Such as {"type": "company"} or {"type": "person"}.')),
            'criteria' => Field::advanced(Field::json('Criteria', 'A list of {"description": "..."} each result must meet.', 'array')),
        ];
    }

    /**
     * A webset's search, as Exa names it.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function websetSearchBody(array $config): array
    {
        return $this->withoutEmpty([
            'query' => $this->requiredString($config, 'query', 'What to find'),
            'count' => $this->intOf($config, 'count') ?? 10,
            'entity' => $this->decoded($config['entity'] ?? null, 'The kind of result'),
            'criteria' => $this->decoded($config['criteria'] ?? null, 'The criteria'),
        ]);
    }
}
