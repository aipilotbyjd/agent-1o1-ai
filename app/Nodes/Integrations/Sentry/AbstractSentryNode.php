<?php

namespace App\Nodes\Integrations\Sentry;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Exceptions\Http\BlockedUrlException;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use App\Services\Http\GuardedHttp;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the Sentry node
 * family. A connection holds an auth token, the organization slug and, for a
 * self-hosted Sentry, the server's address. Calls to sentry.io use the plain client;
 * a self-hosted address must be https and go through `GuardedHttp`, which refuses
 * private and internal hosts on every hop, so a token can't be sent to one.
 *
 * Sentry pages with a `Link` header, read here into `next_cursor`.
 */
abstract class AbstractSentryNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    public const string CLOUD = 'https://sentry.io';

    public function category(): string
    {
        return 'sentry';
    }

    /**
     * The server to call: sentry.io, or a self-hosted https address.
     *
     * @throws InvalidArgumentException when the address can't be used
     */
    public static function baseUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');

        if ($url === '') {
            return self::CLOUD;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('The Sentry address must be a plain https address, like https://sentry.example.com.');
        }

        if ($host === 'sentry.io' || str_ends_with($host, '.sentry.io')) {
            return 'https://'.$host;
        }

        if (preg_match('/^[a-z0-9.-]+$/', $host) !== 1 || ! str_contains($host, '.') || preg_match('/\.(local|localhost|internal|intranet|lan|home|corp)$/', $host) === 1) {
            throw new InvalidArgumentException('The Sentry address must be a public host name, not a private or internal one.');
        }

        return $url;
    }

    /**
     * Call Sentry with a connection's fields. Lists in the query are sent once per
     * value and null values are dropped; an empty `query` stays, because leaving it out
     * means `is:unresolved`.
     *
     * @param  array<string, mixed>  $data  the connection's fields: api_key, base_url
     * @param  array<string, mixed>  $query
     * @param  array<mixed>|null  $body
     *
     * @throws InvalidArgumentException when the connection is incomplete or its address is refused
     */
    public static function send(array $data, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $token = trim((string) ($data['api_key'] ?? ''));

        if ($token === '') {
            throw new InvalidArgumentException('Connect Sentry: an auth token is needed.');
        }

        $base = self::baseUrl((string) ($data['base_url'] ?? ''));
        $queryString = self::queryString($query);
        $url = $base.$path.($queryString === '' ? '' : '?'.$queryString);

        if ($base === self::CLOUD || str_ends_with((string) parse_url($base, PHP_URL_HOST), '.sentry.io')) {
            return Http::withToken($token)->acceptJson()->timeout(30)->send(strtoupper($method), $url, $body === null ? [] : ['json' => $body === [] ? new stdClass : $body]);
        }

        try {
            return app(GuardedHttp::class)->send($method, $url, ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'], $body);
        } catch (BlockedUrlException $exception) {
            throw new InvalidArgumentException('The Sentry address must be a public host name, not a private or internal one.', 0, $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function queryString(array $query): string
    {
        $pairs = [];

        foreach ($query as $name => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if ($item === null) {
                    continue;
                }

                $text = is_bool($item) ? ($item ? 'true' : 'false') : (string) $item;
                $pairs[] = rawurlencode((string) $name).'='.rawurlencode($text);
            }
        }

        return implode('&', $pairs);
    }

    /**
     * The cursor of the next page in a `Link` header, if there are more results.
     */
    public static function nextCursor(Response $response): ?string
    {
        foreach (explode(',', $response->header('Link')) as $link) {
            if (str_contains($link, 'rel="next"') && str_contains($link, 'results="true"') && preg_match('/cursor="([^"]+)"/', $link, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }

    /**
     * Call Sentry and return the decoded body (an empty array for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<mixed>|null  $body
     * @return array<mixed>
     *
     * @throws RuntimeException
     */
    protected function sentry(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->sentryResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<mixed>|null  $body
     */
    protected function sentryResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        return self::send($this->resolveCredentialData($run, $config), $method, $path, $query, $body);
    }

    /**
     * One page of a list, under `$key`, with the cursor of the next page.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  (callable(array<mixed>): array<string, mixed>)|null  $shape
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function listOf(Run $run, array $config, string $path, string $key, array $query = [], ?callable $shape = null): array
    {
        $response = $this->sentryResponse($run, $config, 'GET', $path, [...$query, 'cursor' => $this->text($config, 'cursor')]);

        $this->assertOk($response);

        $items = array_values(array_filter((array) ($response->json() ?? []), 'is_array'));

        return [$key => $shape === null ? $items : array_map($shape, $items), 'count' => count($items), 'next_cursor' => self::nextCursor($response)];
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
        $response = $this->sentryResponse($run, $config, 'GET', $path, $query);

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

        $detail = $response->json('detail');
        $body = $response->json();
        $message = is_string($detail) ? $detail : (is_array($body) ? (string) (json_encode($body) ?: "HTTP {$response->status()}") : "HTTP {$response->status()}");
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Sentry: the auth token was refused.',
            $response->status() === 403 => ' The auth token lacks the scope for this, or the plan does not include it.',
            $response->status() === 404 => ' Check the organization and the id.',
            $response->status() === 429 => ' Sentry is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Sentry error: '.mb_substr($message, 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * The organization slug: the node's, else the connection's.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function org(Run $run, array $config): string
    {
        $value = trim((string) ($config['organization'] ?? ''));

        if ($value === '') {
            $value = trim((string) ($this->resolveCredentialData($run, $config)['organization'] ?? ''));
        }

        if ($value === '') {
            throw new InvalidArgumentException('The organization is required: choose one, or add it to the connection.');
        }

        return $this->slug($value, 'The organization');
    }

    /**
     * A project slug or id from a setting.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function project(array $config): string
    {
        return $this->slug($this->requiredString($config, 'project', 'The project'), 'The project');
    }

    /**
     * A team slug or id from a setting.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function team(array $config): string
    {
        return $this->slug($this->requiredString($config, 'team', 'The team'), 'The team');
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function slug(string $value, string $label): string
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,99}$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} must be a slug: letters, digits, _ and -.");
    }

    /**
     * An issue's numeric id or short id (`PROJECT-123`).
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function issueId(array $config, string $key = 'issue_id'): string
    {
        return $this->slug($this->requiredString($config, $key, 'The issue id'), 'The issue id');
    }

    /**
     * A numeric id, a key's public hash, an event id: anything else that sits in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function pathId(array $config, string $key, string $label): string
    {
        return $this->slug($this->requiredString($config, $key, $label), $label);
    }

    /**
     * A release version. These hold `@`, `+` and sometimes `/`, so it is encoded rather than restricted.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function version(array $config): string
    {
        $value = $this->requiredString($config, 'version', 'The release version');

        if (mb_strlen($value) > 200 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1 || in_array($value, ['.', '..'], true)) {
            throw new InvalidArgumentException('That is not a valid release version.');
        }

        return rawurlencode($value);
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
    protected function items(array $config, string $key): array
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
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default = 50, int $max = 100): int
    {
        return max(1, min($max, (int) ($config['limit'] ?? $default)));
    }

    /**
     * Only the settings that are filled in, for an update.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, string>  $map  setting => API field
     * @return array<string, mixed>
     */
    protected function filledOf(array $config, array $map): array
    {
        $body = [];

        foreach ($map as $key => $field) {
            $value = $config[$key] ?? null;

            if ($value !== null && $value !== '') {
                $body[$field] = is_string($value) ? trim($value) : $value;
            }
        }

        return $body;
    }

    /**
     * The fields shared by every issue a node returns.
     *
     * @param  array<mixed>  $issue
     * @return array<string, mixed>
     */
    protected function shapeIssue(array $issue): array
    {
        return ['id' => isset($issue['id']) ? (string) $issue['id'] : null, 'short_id' => $issue['shortId'] ?? null, 'title' => $issue['title'] ?? null, 'culprit' => $issue['culprit'] ?? null, 'level' => $issue['level'] ?? null, 'status' => $issue['status'] ?? null, 'substatus' => $issue['substatus'] ?? null, 'priority' => $issue['priority'] ?? null, 'platform' => $issue['platform'] ?? null, 'project' => data_get($issue, 'project.slug'), 'count' => $issue['count'] ?? null, 'user_count' => $issue['userCount'] ?? null, 'assigned_to' => data_get($issue, 'assignedTo.email') ?? data_get($issue, 'assignedTo.name'), 'first_seen' => $issue['firstSeen'] ?? null, 'last_seen' => $issue['lastSeen'] ?? null, 'url' => $issue['permalink'] ?? null, 'metadata' => $issue['metadata'] ?? null];
    }

    /**
     * @param  array<mixed>  $event
     * @return array<string, mixed>
     */
    protected function shapeEvent(array $event): array
    {
        return ['id' => $event['eventID'] ?? $event['id'] ?? null, 'issue_id' => $event['groupID'] ?? null, 'project_id' => $event['projectID'] ?? null, 'title' => $event['title'] ?? null, 'message' => $event['message'] ?? null, 'type' => $event['event.type'] ?? $event['type'] ?? null, 'platform' => $event['platform'] ?? null, 'culprit' => $event['culprit'] ?? null, 'location' => $event['location'] ?? null, 'user' => $event['user'] ?? null, 'tags' => $event['tags'] ?? [], 'created_at' => $event['dateCreated'] ?? null, 'received_at' => $event['dateReceived'] ?? null, 'metadata' => $event['metadata'] ?? null, 'entries' => $event['entries'] ?? null, 'contexts' => $event['contexts'] ?? null];
    }

    /**
     * @param  array<mixed>  $release
     * @return array<string, mixed>
     */
    protected function shapeRelease(array $release): array
    {
        return ['id' => isset($release['id']) ? (string) $release['id'] : null, 'version' => $release['version'] ?? null, 'short_version' => $release['shortVersion'] ?? null, 'status' => $release['status'] ?? null, 'ref' => $release['ref'] ?? null, 'url' => $release['url'] ?? null, 'created_at' => $release['dateCreated'] ?? null, 'released_at' => $release['dateReleased'] ?? null, 'new_issues' => $release['newGroups'] ?? null, 'commit_count' => $release['commitCount'] ?? null, 'deploy_count' => $release['deployCount'] ?? null, 'projects' => array_values(array_filter(array_map(fn (mixed $project): mixed => is_array($project) ? ($project['slug'] ?? null) : null, (array) ($release['projects'] ?? []))))];
    }

    /**
     * @param  array<mixed>  $project
     * @return array<string, mixed>
     */
    protected function shapeProject(array $project): array
    {
        return ['id' => isset($project['id']) ? (string) $project['id'] : null, 'slug' => $project['slug'] ?? null, 'name' => $project['name'] ?? null, 'platform' => $project['platform'] ?? null, 'team' => data_get($project, 'team.slug'), 'created_at' => $project['dateCreated'] ?? null, 'environments' => $project['environments'] ?? null];
    }

    /**
     * @param  array<mixed>  $team
     * @return array<string, mixed>
     */
    protected function shapeTeam(array $team): array
    {
        return ['id' => isset($team['id']) ? (string) $team['id'] : null, 'slug' => $team['slug'] ?? null, 'name' => $team['name'] ?? null, 'member_count' => $team['memberCount'] ?? null, 'created_at' => $team['dateCreated'] ?? null];
    }

    /**
     * @param  array<mixed>  $member
     * @return array<string, mixed>
     */
    protected function shapeMember(array $member): array
    {
        return ['id' => isset($member['id']) ? (string) $member['id'] : null, 'email' => $member['email'] ?? null, 'name' => $member['name'] ?? null, 'role' => $member['orgRole'] ?? $member['role'] ?? null, 'pending' => $member['pending'] ?? null, 'created_at' => $member['dateCreated'] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    protected function organizationField(): array
    {
        return Field::advanced(Field::dynamic('Organization', 'sentry.organizations', "Empty uses the connection's organization.", 'my-org'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function projectField(string $title = 'Project'): array
    {
        return Field::dynamic($title, 'sentry.projects', 'Pick one, or type its slug.', 'backend', uses: ['organization']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function teamField(string $title = 'Team'): array
    {
        return Field::dynamic($title, 'sentry.teams', 'Pick one, or type its slug.', 'engineering', uses: ['organization']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function memberField(): array
    {
        return Field::dynamic('Member', 'sentry.members', 'Pick one, or type the member id.', uses: ['organization']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function pageFields(int $default = 50): array
    {
        return [
            'limit' => Field::integer('Max results', null, $default, 1, 100),
            'cursor' => Field::advanced(Field::text('Page cursor', 'The "next_cursor" of the last call, for the next page.')),
        ];
    }
}
