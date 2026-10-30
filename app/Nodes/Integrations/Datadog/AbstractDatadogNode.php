<?php

namespace App\Nodes\Integrations\Datadog;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the Datadog
 * node family. A connection holds an API key, an application key and a site. The
 * site only ever picks from a fixed list of Datadog hosts, so the keys can't be
 * sent anywhere else: most calls go to `api.{site}`, log and event intake to their
 * own subdomains of the same site.
 *
 * A v2 record is `{id, type, attributes}`; results are flattened to one level so a
 * later step can read `{{step.title}}` rather than `{{step.attributes.title}}`.
 */
abstract class AbstractDatadogNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    /**
     * Each site's code and the host it lives on.
     *
     * @var array<string, string>
     */
    public const array SITES = [
        'us1' => 'datadoghq.com',
        'us3' => 'us3.datadoghq.com',
        'us5' => 'us5.datadoghq.com',
        'eu1' => 'datadoghq.eu',
        'ap1' => 'ap1.datadoghq.com',
        'ap2' => 'ap2.datadoghq.com',
        'uk1' => 'uk1.datadoghq.com',
        'gov' => 'ddog-gov.com',
        'gov2' => 'us2.ddog-gov.com',
    ];

    public function category(): string
    {
        return 'datadog';
    }

    /**
     * The host of a site, given its code (`eu1`) or the host itself. Empty is us1.
     *
     * @throws InvalidArgumentException when it is not a Datadog site
     */
    public static function siteHost(string $site): string
    {
        $site = strtolower(trim((string) preg_replace('~^https?://(app\.|api\.)?~i', '', trim($site)), ' /'));

        if ($site === '') {
            return self::SITES['us1'];
        }

        if (isset(self::SITES[$site])) {
            return self::SITES[$site];
        }

        return in_array($site, self::SITES, true) ? $site : throw new InvalidArgumentException('The Datadog site must be one of '.implode(', ', array_keys(self::SITES)).'.');
    }

    /**
     * Call Datadog with a connection's fields. Lists in the query are sent once per
     * value and null values are dropped. Redirects aren't followed, so the keys stay
     * on the site.
     *
     * @param  array<string, mixed>  $data  the connection's fields: api_key, app_key, site
     * @param  array<string, mixed>  $query
     * @param  array<mixed>|null  $body  sent as JSON
     *
     * @throws InvalidArgumentException when the connection is incomplete
     */
    public static function send(array $data, string $method, string $path, array $query = [], ?array $body = null, string $subdomain = 'api'): Response
    {
        $apiKey = trim((string) ($data['api_key'] ?? ''));
        $appKey = trim((string) ($data['app_key'] ?? ''));

        if ($apiKey === '' || $appKey === '') {
            throw new InvalidArgumentException('Connect Datadog: both the API key and the application key are needed.');
        }

        $url = 'https://'.$subdomain.'.'.self::siteHost((string) ($data['site'] ?? '')).$path;
        $queryString = self::queryString($query);
        $options = $body === null ? [] : ['json' => $body === [] ? new stdClass : $body];

        // This one endpoint is only documented for `text/json`.
        $contentType = $path === '/api/v1/distribution_points' ? ['Content-Type' => 'text/json'] : [];

        return Http::withHeaders(['DD-API-KEY' => $apiKey, 'DD-APPLICATION-KEY' => $appKey, ...$contentType])
            ->acceptJson()
            ->withoutRedirecting()
            ->timeout(30)
            ->send(strtoupper($method), $url.($queryString === '' ? '' : '?'.$queryString), $options);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function queryString(array $query): string
    {
        $pairs = [];

        foreach ($query as $name => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if ($item === null || $item === '') {
                    continue;
                }

                $text = is_bool($item) ? ($item ? 'true' : 'false') : (string) $item;
                $pairs[] = rawurlencode((string) $name).'='.rawurlencode($text);
            }
        }

        return implode('&', $pairs);
    }

    /**
     * Call Datadog and return the decoded body (an empty array for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<mixed>|null  $body
     * @return array<mixed>
     *
     * @throws RuntimeException
     */
    protected function datadog(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null, string $subdomain = 'api'): array
    {
        $response = $this->datadogResponse($run, $config, $method, $path, $query, $body, $subdomain);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Call Datadog and return the response as it is, for a node that treats an error as an answer.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<mixed>|null  $body
     */
    protected function datadogResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null, string $subdomain = 'api'): Response
    {
        return self::send($this->resolveCredentialData($run, $config), $method, $path, $query, $body, $subdomain);
    }

    /**
     * A record that may not exist: the body, or null on a 404.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return array<mixed>|null
     *
     * @throws RuntimeException
     */
    protected function find(Run $run, array $config, string $path, array $query = []): ?array
    {
        $response = $this->datadogResponse($run, $config, 'GET', $path, $query);

        if ($response->status() === 404) {
            return null;
        }

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

        $message = self::errorMessage($response);
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Datadog: the API key was refused.',
            $response->status() === 403 => ' The application key lacks the permission for this, or is on another site.',
            $response->status() === 404 => ' Check the id, and that the connection is on the right Datadog site.',
            $response->status() === 429 => ' Datadog is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Datadog error: '.mb_substr($message, 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Datadog's `errors` are plain strings on v1 and `{title, detail}` objects on v2.
     */
    public static function errorMessage(Response $response): string
    {
        $errors = $response->json('errors');

        if (is_array($errors) && $errors !== []) {
            $first = reset($errors);

            if (is_array($first)) {
                return (string) ($first['detail'] ?? $first['title'] ?? "HTTP {$response->status()}");
            }

            return is_scalar($first) ? implode('; ', array_map('strval', array_filter($errors, 'is_scalar'))) : "HTTP {$response->status()}";
        }

        return (string) ($response->json('error') ?? $response->json('message') ?? "HTTP {$response->status()}");
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function requiredString(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        return $value !== '' ? $value : throw new InvalidArgumentException("{$label} is required.");
    }

    /**
     * A numeric id from a setting, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function numericId(array $config, string $key, string $label): string
    {
        $value = $this->requiredString($config, $key, $label);

        return preg_match('/^\d{1,20}$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} must be a number.");
    }

    /**
     * Any other id (a dashboard's `abc-def-ghi`, a host name, a metric name), checked
     * so it can sit in a URL, then encoded.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function pathId(array $config, string $key, string $label): string
    {
        $value = $this->requiredString($config, $key, $label);

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:@~+-]{0,254}$/', $value) !== 1 || str_contains($value, '..')) {
            throw new InvalidArgumentException("{$label} may only hold letters, digits and . _ : @ ~ + -");
        }

        return rawurlencode($value);
    }

    /**
     * A text setting, or null when it is empty.
     *
     * @param  array<string, mixed>  $config
     */
    protected function text(array $config, string $key): ?string
    {
        $raw = $config[$key] ?? '';
        $value = trim(is_array($raw) ? implode(',', array_filter($raw, 'is_scalar')) : (string) $raw);

        return $value === '' ? null : $value;
    }

    /**
     * A list setting, given as a list or comma-separated text.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function listOf(array $config, string $key): array
    {
        $value = $config[$key] ?? [];
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(fn (mixed $item): string => trim((string) $item), $items), fn (string $item): bool => $item !== ''));
    }

    /**
     * A JSON setting: an array as it is, or text holding JSON. Null when empty.
     *
     * @param  array<string, mixed>  $config
     * @return array<mixed>|null
     *
     * @throws InvalidArgumentException
     */
    protected function jsonOf(array $config, string $key, string $label): ?array
    {
        $value = $config[$key] ?? null;

        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : throw new InvalidArgumentException("{$label} must be valid JSON.");
    }

    /**
     * A required JSON setting.
     *
     * @param  array<string, mixed>  $config
     * @return array<mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function requiredJson(array $config, string $key, string $label): array
    {
        return $this->jsonOf($config, $key, $label) ?? throw new InvalidArgumentException("{$label} is required.");
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default = 50, int $max = 1000): int
    {
        return max(1, min($max, (int) ($config['limit'] ?? $default)));
    }

    /**
     * A moment as Unix seconds, from a number, an ISO date or `now`, `now-15m`, `now-2h`, `now-7d`.
     *
     * @throws InvalidArgumentException
     */
    protected function unixTime(mixed $value, string $label): int
    {
        $value = trim((string) $value);

        if (ctype_digit($value)) {
            return (int) $value;
        }

        if (preg_match('/^now(?:-(\d+)([smhdw]))?$/', $value, $match) === 1) {
            $seconds = ['s' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400, 'w' => 604800];

            return time() - (isset($match[1]) ? (int) $match[1] * $seconds[$match[2]] : 0);
        }

        $time = strtotime($value);

        return $time !== false ? $time : throw new InvalidArgumentException("{$label} must be a date, Unix seconds, or like now-15m.");
    }

    /**
     * The `{filter, page, sort}` body shared by Datadog's search endpoints.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $filter  more filter settings
     * @return array<string, mixed>
     */
    protected function searchBody(array $config, array $filter = [], string $defaultFrom = 'now-15m', string $sort = '-timestamp'): array
    {
        $page = ['limit' => $this->limit($config)];

        if (filled($config['cursor'] ?? null)) {
            $page['cursor'] = (string) $config['cursor'];
        }

        return [
            'filter' => ['from' => $this->text($config, 'from') ?? $defaultFrom, 'to' => $this->text($config, 'to') ?? 'now', 'query' => $this->text($config, 'query') ?? '*', ...$filter],
            'page' => $page,
            'sort' => ($config['sort'] ?? $sort) === 'timestamp' ? 'timestamp' : '-timestamp',
        ];
    }

    /**
     * A v2 record on one level: `id`, `type` and its attributes (kept under `attributes` if they clash).
     *
     * @param  array<mixed>  $record
     * @return array<string, mixed>
     */
    protected function flatten(array $record): array
    {
        $attributes = is_array($record['attributes'] ?? null) ? $record['attributes'] : [];

        return ['id' => isset($record['id']) ? (string) $record['id'] : null, 'type' => $record['type'] ?? null, ...array_diff_key($attributes, ['id' => 1, 'type' => 1])];
    }

    /**
     * Every record of a v2 list (`data`) on one level.
     *
     * @param  array<mixed>  $body
     * @return list<array<string, mixed>>
     */
    protected function records(array $body): array
    {
        return array_map($this->flatten(...), array_values(array_filter((array) ($body['data'] ?? []), 'is_array')));
    }

    /**
     * @param  array<mixed>  $monitor
     * @return array<string, mixed>
     */
    protected function shapeMonitor(array $monitor): array
    {
        $tags = $monitor['tags'] ?? [];

        return ['id' => $monitor['id'] ?? null, 'name' => $monitor['name'] ?? null, 'type' => $monitor['type'] ?? null, 'query' => $monitor['query'] ?? null, 'message' => $monitor['message'] ?? null, 'status' => $monitor['overall_state'] ?? $monitor['status'] ?? null, 'priority' => $monitor['priority'] ?? null, 'tags' => is_array($tags) ? $tags : [], 'creator' => data_get($monitor, 'creator.email'), 'created_at' => $monitor['created'] ?? null, 'modified_at' => $monitor['modified'] ?? null, 'last_triggered_at' => is_numeric($monitor['last_triggered_ts'] ?? null) ? gmdate('c', (int) $monitor['last_triggered_ts']) : null, 'options' => $monitor['options'] ?? null, 'state' => $monitor['state'] ?? null];
    }

    /**
     * @param  array<mixed>  $event
     * @return array<string, mixed>
     */
    protected function shapeEvent(array $event): array
    {
        $attributes = (array) ($event['attributes'] ?? []);
        $inner = (array) ($attributes['attributes'] ?? []);

        return ['id' => isset($event['id']) ? (string) $event['id'] : null, 'title' => $inner['title'] ?? null, 'message' => $attributes['message'] ?? null, 'timestamp' => $attributes['timestamp'] ?? null, 'tags' => $attributes['tags'] ?? [], 'status' => $inner['status'] ?? null, 'priority' => $inner['priority'] ?? null, 'host' => $inner['hostname'] ?? null, 'service' => $inner['service'] ?? null, 'source' => $inner['source_type_name'] ?? null, 'monitor_id' => $inner['monitor_id'] ?? null];
    }

    /**
     * @param  array<mixed>  $log
     * @return array<string, mixed>
     */
    protected function shapeLog(array $log): array
    {
        $attributes = (array) ($log['attributes'] ?? []);

        return ['id' => isset($log['id']) ? (string) $log['id'] : null, 'message' => $attributes['message'] ?? null, 'status' => $attributes['status'] ?? null, 'service' => $attributes['service'] ?? null, 'host' => $attributes['host'] ?? null, 'timestamp' => $attributes['timestamp'] ?? null, 'tags' => $attributes['tags'] ?? [], 'attributes' => $attributes['attributes'] ?? []];
    }

    /**
     * @param  array<mixed>  $signal
     * @return array<string, mixed>
     */
    protected function shapeSignal(array $signal): array
    {
        $attributes = (array) ($signal['attributes'] ?? []);
        $custom = (array) ($attributes['custom'] ?? $attributes['attributes'] ?? []);

        return ['id' => isset($signal['id']) ? (string) $signal['id'] : null, 'message' => $attributes['message'] ?? null, 'timestamp' => $attributes['timestamp'] ?? null, 'tags' => $attributes['tags'] ?? [], 'severity' => data_get($custom, 'status'), 'title' => data_get($custom, 'title'), 'state' => data_get($custom, 'workflow.triage.state'), 'attributes' => $custom];
    }

    /**
     * @param  array<mixed>  $incident
     * @return array<string, mixed>
     */
    protected function shapeIncident(array $incident): array
    {
        $flat = $this->flatten($incident);

        return ['state' => data_get($flat, 'fields.state.value') ?? data_get($flat, 'state'), 'severity' => data_get($flat, 'fields.severity.value') ?? data_get($flat, 'severity'), ...$flat];
    }

    /**
     * @return array<string, mixed>
     */
    protected function monitorField(string $title = 'Monitor'): array
    {
        return Field::dynamic($title, 'datadog.monitors', 'Pick one, or type its numeric id.', '12345');
    }

    /**
     * @return array<string, mixed>
     */
    protected function dashboardField(): array
    {
        return Field::dynamic('Dashboard', 'datadog.dashboards', 'Pick one, or type its id.', 'abc-def-ghi');
    }

    /**
     * @return array<string, mixed>
     */
    protected function sloField(): array
    {
        return Field::dynamic('SLO', 'datadog.slos', 'Pick one, or type its id.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function syntheticField(): array
    {
        return Field::dynamic('Synthetic test', 'datadog.synthetics', 'Pick one, or type its public id.', 'abc-def-ghi');
    }

    /**
     * @return array<string, mixed>
     */
    protected function userField(string $title = 'User'): array
    {
        return Field::dynamic($title, 'datadog.users', 'Pick one, or type the user id.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function teamField(): array
    {
        return Field::dynamic('Team', 'datadog.teams', 'Pick one, or type the team id.');
    }

    /**
     * The time window, query and page settings of a search node.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function searchFields(string $queryHint, string $defaultFrom = 'now-15m'): array
    {
        return [
            'query' => Field::text('Query', $queryHint, '*'),
            'from' => Field::text('From', "Default {$defaultFrom}. A date, or relative like now-1h.", $defaultFrom),
            'to' => Field::text('To', 'Default now.', 'now'),
            'limit' => Field::integer('Max results', null, 50, 1, 1000),
            'sort' => Field::advanced(Field::select('Order', ['-timestamp' => 'Newest first', 'timestamp' => 'Oldest first'], null, '-timestamp')),
            'cursor' => Field::advanced(Field::text('Page cursor', 'The "next_cursor" of the last call, for the next page.')),
        ];
    }
}
