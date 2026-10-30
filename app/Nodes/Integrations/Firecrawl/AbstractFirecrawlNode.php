<?php

namespace App\Nodes\Integrations\Firecrawl;

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
 * Shared request, error handling, validation and result shaping for the Firecrawl
 * node family (API v2, the key as a bearer token). Crawls, batch scrapes, extracts
 * and agent runs are jobs: a Start node returns an id at once, and a Get Status
 * node reads the result, so no step holds a worker while a site is read.
 */
abstract class AbstractFirecrawlNode implements DeclaresEffect, NodeContract
{
    use ReadsApiSettings;
    use ResolvesConnectorCredential;

    private const string API_URL = 'https://api.firecrawl.dev/v2';

    public function category(): string
    {
        return 'firecrawl';
    }

    /**
     * Call Firecrawl and return the decoded body (an empty array for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $body
     * @param  array<string, mixed>  $query  null values are dropped
     * @return array<int|string, mixed>
     *
     * @throws RuntimeException
     */
    protected function firecrawl(Run $run, array $config, string $method, string $path, ?array $body = null, array $query = [], int $timeout = 30): array
    {
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $body;
        }

        $response = Http::withToken($this->resolveAccessToken($run, $config))->acceptJson()->timeout($timeout)->send(strtoupper($method), self::API_URL.$path, $options);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Send a file's bytes to Firecrawl and return the decoded body.
     *
     * @param  array<string, mixed>  $config
     * @return array<int|string, mixed>
     *
     * @throws RuntimeException
     */
    protected function firecrawlUpload(Run $run, array $config, string $path, string $name, string $bytes): array
    {
        $response = Http::withToken($this->resolveAccessToken($run, $config))->acceptJson()->timeout(75)->attach('file', $bytes, $name)->post(self::API_URL.$path);

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
            $response->status() === 401 => ' Reconnect Firecrawl: the API key was refused.',
            $response->status() === 402 => ' The Firecrawl team is out of credits, or its plan does not include this.',
            $response->status() === 403 => ' The Firecrawl key is not allowed to do that.',
            $response->status() === 429 => ' Firecrawl is rate limiting the team; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Firecrawl error: '.mb_substr($message, 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * The shared scrape settings.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function scrapeFields(): array
    {
        return [
            'formats' => Field::list('Formats', 'markdown, html, rawHtml, links, summary, images or screenshot. Leave empty for markdown.', 'markdown'),
            'json_prompt' => Field::advanced(Field::textarea('Extract with a prompt', 'Asks for structured JSON from the page, described in words.')),
            'json_schema' => Field::advanced(Field::json('Extract to a schema', 'Asks for structured JSON from the page, in the shape of this JSON schema.')),
            'only_main_content' => Field::advanced(Field::boolean('Only main content', 'Leaves out headers, footers and navigation.', true)),
            'include_tags' => Field::advanced(Field::list('Only these tags', 'HTML tags, classes or ids to keep.', 'article, .content')),
            'exclude_tags' => Field::advanced(Field::list('Leave out tags', 'HTML tags, classes or ids to remove.', 'nav, .ads')),
            'wait_for' => Field::advanced(Field::integer('Wait first (ms)', 'Waits this long for the page before reading it.', null, 0, 60000)),
            'scrape_timeout' => Field::advanced(Field::integer('Timeout (ms)', 'How long Firecrawl may try this page.', null, 1000, 60000)),
            'max_age' => Field::advanced(Field::integer('Accept a cached copy up to (ms)', 'Use 0 to always read the page fresh.', null, 0)),
            'mobile' => Field::advanced(Field::boolean('As a phone')),
            'block_ads' => Field::advanced(Field::boolean('Block ads and cookie banners', null, true)),
            'proxy' => Field::advanced(Field::select('Proxy', ['auto', 'basic', 'enhanced'], null, 'auto')),
            'country' => Field::advanced(Field::text('Country', 'A two-letter country code to read the page from.', 'US')),
            'languages' => Field::advanced(Field::list('Languages', 'Preferred languages, in order.', 'en-US')),
            'request_headers' => Field::advanced(Field::keyValue('Request headers', 'Sent to the page, such as a cookie.')),
            'actions' => Field::advanced(Field::json('Browser actions', 'Steps to take before reading: click, write, scroll, wait, press, screenshot, as in Firecrawl\'s docs.', 'array')),
        ];
    }

    /**
     * The shared scrape settings, as Firecrawl names them.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function scrapeOptions(array $config): array
    {
        $formats = $this->stringList($config['formats'] ?? []);
        $prompt = $this->textOf($config, 'json_prompt');
        $schema = $this->decoded($config['json_schema'] ?? null, 'The schema');

        if ($prompt !== null || $schema !== null) {
            $formats[] = $this->withoutEmpty(['type' => 'json', 'prompt' => $prompt, 'schema' => $schema]);
        }

        $country = $this->textOf($config, 'country');
        $languages = $this->stringList($config['languages'] ?? []);

        return $this->withoutEmpty([
            'formats' => $formats,
            'onlyMainContent' => $this->boolOf($config, 'only_main_content'),
            'includeTags' => $this->stringList($config['include_tags'] ?? []),
            'excludeTags' => $this->stringList($config['exclude_tags'] ?? []),
            'waitFor' => $this->intOf($config, 'wait_for'),
            'timeout' => $this->intOf($config, 'scrape_timeout'),
            'maxAge' => $this->intOf($config, 'max_age'),
            'mobile' => $this->boolOf($config, 'mobile'),
            'blockAds' => $this->boolOf($config, 'block_ads'),
            'proxy' => $this->textOf($config, 'proxy'),
            'location' => $this->withoutEmpty(['country' => $country, 'languages' => $languages]),
            'headers' => $this->decoded($config['request_headers'] ?? null, 'The request headers'),
            'actions' => $this->decoded($config['actions'] ?? null, 'The browser actions'),
        ]);
    }

    /**
     * Where Firecrawl should post a job's events, for a Start node that offers it.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function webhookFields(string $events): array
    {
        return [
            'webhook_url' => Field::advanced(Field::text('Send events to', 'A web address told about this job as it runs: a Webhook trigger\'s URL, for instance.', 'https://')),
            'webhook_events' => Field::advanced(Field::list('Events to send', "{$events}. Leave empty for all of them.")),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{url: string, events?: list<string>}|null
     *
     * @throws InvalidArgumentException
     */
    protected function webhook(array $config): ?array
    {
        if ($this->textOf($config, 'webhook_url') === null) {
            return null;
        }

        return $this->withoutEmpty(['url' => $this->webUrl($config, 'webhook_url', 'The events address'), 'events' => $this->stringList($config['webhook_events'] ?? [])]);
    }

    /**
     * A page Firecrawl read.
     *
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    protected function shapePage(array $page): array
    {
        return $this->withoutEmpty([
            'url' => data_get($page, 'metadata.sourceURL') ?? data_get($page, 'metadata.url'),
            'title' => data_get($page, 'metadata.title'),
            'status_code' => data_get($page, 'metadata.statusCode'),
            'scrape_id' => data_get($page, 'metadata.scrapeId'),
            'markdown' => $page['markdown'] ?? null,
            'html' => $page['html'] ?? null,
            'raw_html' => $page['rawHtml'] ?? null,
            'links' => $page['links'] ?? null,
            'summary' => $page['summary'] ?? null,
            'images' => $page['images'] ?? null,
            'screenshot' => $page['screenshot'] ?? null,
            'json' => $page['json'] ?? null,
            'actions' => $page['actions'] ?? null,
            'warning' => $page['warning'] ?? null,
            'metadata' => $page['metadata'] ?? null,
        ]);
    }

    /**
     * A crawl or batch scrape: how far it is, and the pages read so far.
     *
     * @param  array<string, mixed>  $job
     * @return array<string, mixed>
     */
    protected function shapeJob(string $id, array $job, int $maxPages): array
    {
        $pages = array_values(array_filter((array) ($job['data'] ?? []), 'is_array'));

        return [
            'id' => $id,
            'status' => $job['status'] ?? null,
            'finished' => in_array($job['status'] ?? null, ['completed', 'failed', 'cancelled'], true),
            'total' => $job['total'] ?? null,
            'completed' => $job['completed'] ?? null,
            'credits_used' => $job['creditsUsed'] ?? null,
            'expires_at' => $job['expiresAt'] ?? null,
            'more_pages' => filled($job['next'] ?? null) || count($pages) > $maxPages,
            'pages' => array_map($this->shapePage(...), array_slice($pages, 0, $maxPages)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function monitorField(): array
    {
        return Field::dynamic('Monitor', 'firecrawl.monitors', null, 'Pick a monitor.');
    }

    /**
     * What a monitor watches: page links, or a whole site, on a schedule.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function monitorBody(array $config): array
    {
        $targets = $this->decoded($config['targets'] ?? null, 'The targets');
        $urls = $this->stringList($config['urls'] ?? []);

        if ($targets === null && $urls !== []) {
            $targets = [['type' => 'scrape', 'urls' => array_map($this->checkedUrl(...), $urls)]];
        }

        $cron = $this->textOf($config, 'schedule_cron');
        $text = $this->textOf($config, 'schedule_text');
        $webhook = $this->webhook($config);
        $recipients = $this->stringList($config['email_recipients'] ?? []);

        return $this->withoutEmpty([
            'name' => $this->textOf($config, 'name'),
            'schedule' => $this->withoutEmpty(['cron' => $cron, 'text' => $text, 'timezone' => $this->textOf($config, 'timezone')]),
            'targets' => $targets,
            'goal' => $this->textOf($config, 'goal'),
            'retentionDays' => $this->intOf($config, 'retention_days'),
            'webhook' => $webhook,
            'notification' => $recipients === [] ? null : ['email' => ['enabled' => true, 'recipients' => $recipients]],
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function monitorFields(): array
    {
        return [
            'urls' => Field::list('Pages to watch', 'The links to check each time.', 'https://example.com/pricing'),
            'schedule_cron' => Field::text('Schedule (cron)', 'When to check, such as every morning.', '0 9 * * *'),
            'schedule_text' => Field::text('Or schedule in words', null, 'every day at 9am'),
            'timezone' => Field::advanced(Field::text('Time zone', null, 'UTC')),
            'goal' => Field::advanced(Field::textarea('What counts as a change', 'In words: Firecrawl judges changes against this.')),
            'targets' => Field::advanced(Field::json('Targets', 'Used instead of Pages to watch: scrape and crawl targets, as in Firecrawl\'s docs.', 'array')),
            'retention_days' => Field::advanced(Field::integer('Keep results (days)', null, null, 1)),
            'email_recipients' => Field::advanced(Field::emails('Email changes to')),
            ...$this->webhookFields('monitor.page, monitor.check.completed'),
        ];
    }

    /**
     * Whether a monitor check has ended.
     *
     * @param  array<string, mixed>  $check
     */
    protected function checkIsDone(array $check): bool
    {
        return in_array($check['status'] ?? null, ['completed', 'partial', 'failed'], true);
    }
}
