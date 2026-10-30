<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use App\Services\Http\GuardedHttp;
use App\Services\Http\SsrfGuard;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the Zendesk
 * node family. Zendesk is called at the account's own subdomain, with an agent's
 * email and API token (`{email}/token`) or, when no email is given, an OAuth token
 * as a bearer. Ids are numbers (a side conversation's is a uuid); a list pages with
 * `per_page` and answers with the next page's address, which is only followed on
 * the account's own host.
 */
abstract class AbstractZendeskNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    public function category(): string
    {
        return 'zendesk';
    }

    /**
     * The API root of a subdomain, as typed or pasted: `acme`, `acme.zendesk.com` or a full address.
     *
     * @throws InvalidArgumentException
     */
    public static function apiRoot(string $subdomain): string
    {
        $host = preg_replace('~^https?://~i', '', strtolower(trim($subdomain))) ?? '';
        $host = explode('/', $host)[0];
        $host = str_ends_with($host, '.zendesk.com') ? substr($host, 0, -strlen('.zendesk.com')) : $host;

        if (preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $host) !== 1) {
            throw new InvalidArgumentException('Enter the Zendesk subdomain, like acme for acme.zendesk.com.');
        }

        return "https://{$host}.zendesk.com/api/v2";
    }

    /**
     * An authorised client for the connection's fields: an API token with the
     * agent's email, or an OAuth token when there is no email.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public static function client(array $data): PendingRequest
    {
        $token = trim((string) ($data['api_key'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));

        if ($token === '') {
            throw new InvalidArgumentException('Add the Zendesk API token to the connection.');
        }

        $request = $email === '' ? Http::withToken($token) : Http::withBasicAuth($email.'/token', $token);

        return $request->acceptJson()->timeout(30);
    }

    /**
     * Call Zendesk and return the decoded body (an empty array for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function zendesk(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->zendeskResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Call Zendesk and return the response as it is, for a node that treats a 404 as an answer.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function zendeskResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $data = $this->resolveCredentialData($run, $config);
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $body;
        }

        return self::client($data)->send(strtoupper($method), self::apiRoot((string) ($data['subdomain'] ?? '')).$path, $options);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $error = $response->json('error');
        $message = (string) ($response->json('description') ?? $response->json('error.message') ?? (is_string($error) ? $error : null) ?? $response->json('errors.0.title') ?? "HTTP {$response->status()}");
        $details = $response->json('details');
        $detail = is_array($details) ? (string) data_get(array_values($details), '0.0.description', '') : '';
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Zendesk: the email and API token were refused.',
            $response->status() === 403 => ' The Zendesk user is not allowed to do that (some calls need an admin).',
            $response->status() === 429 => ' Zendesk is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Zendesk error: '.mb_substr($message.($detail !== '' ? " {$detail}" : ''), 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Read a list page by page until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function listAll(Run $run, array $config, string $path, string $key, array $query, int $limit): array
    {
        $data = $this->resolveCredentialData($run, $config);
        $root = self::apiRoot((string) ($data['subdomain'] ?? ''));
        $client = self::client($data);
        $records = [];
        $url = $root.$path;
        $query = array_filter(array_key_exists('page', $query) ? $query : [...$query, 'per_page' => 100], fn (mixed $value): bool => $value !== null);

        do {
            $response = $client->get($url, $query);
            $this->assertOk($response);

            $rows = array_values(array_filter((array) ($response->json($key) ?? []), 'is_array'));
            array_push($records, ...$rows);

            $next = $response->json('meta.has_more') === false ? null : ($response->json('links.next') ?? $response->json('after_url') ?? $response->json('next_page'));
            $url = is_string($next) && str_starts_with($next, $root.'/') ? $next : null;
            $query = [];
        } while ($url !== null && $rows !== [] && count($records) < $limit);

        return array_slice($records, 0, $limit);
    }

    /**
     * A list node's whole answer: the shaped records under `$key`, and how many.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  (callable(array<string, mixed>): array<string, mixed>)|null  $shape
     * @return array<string, mixed>
     */
    protected function listing(Run $run, array $config, string $path, string $key, array $query = [], ?callable $shape = null, int $default = 100): array
    {
        $rows = $this->listAll($run, $config, $path, $key, $query, $this->limit($config, $default));

        return [$key => $shape === null ? $rows : array_map($shape, $rows), 'count' => count($rows)];
    }

    /**
     * Read one record, or say it was not found.
     *
     * @param  array<string, mixed>  $config
     * @param  (callable(array<string, mixed>): array<string, mixed>)|null  $shape
     * @return array<string, mixed>
     */
    protected function findOne(Run $run, array $config, string $path, string $key, string $id, ?callable $shape = null): array
    {
        $response = $this->zendeskResponse($run, $config, 'GET', $path);

        if ($response->status() === 404) {
            return ['found' => false, 'id' => $id];
        }

        $this->assertOk($response);
        $record = (array) ($response->json($key) ?? []);

        return ['found' => true, ...($shape === null ? $record : $shape($record))];
    }

    /**
     * A plain id (a number) from a setting, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function numericId(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return preg_match('/^\d{1,25}$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} must be a Zendesk id (a number).");
    }

    /**
     * An optional id: as a number, or null when the setting is empty.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function optionalId(array $config, string $key, string $label): ?int
    {
        return filled($config[$key] ?? null) ? (int) $this->numericId($config, $key, $label) : null;
    }

    /**
     * A text id that sits in a URL, such as a side conversation's uuid.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function safeId(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return preg_match('/^[A-Za-z0-9_-]{1,100}$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} may only hold letters, digits, _ and -.");
    }

    /**
     * Several numeric ids from a list or a comma separated setting.
     *
     * @param  array<string, mixed>  $config
     * @return list<int>
     *
     * @throws InvalidArgumentException
     */
    protected function idList(array $config, string $key, string $label, int $max = 100): array
    {
        $value = $config[$key] ?? [];
        $parts = is_array($value) ? $value : explode(',', (string) $value);
        $ids = array_values(array_filter(array_map(fn (mixed $part): string => trim((string) $part), $parts), fn (string $part): bool => $part !== ''));

        if ($ids === []) {
            throw new InvalidArgumentException("{$label} is required.");
        }

        if (count($ids) > $max) {
            throw new InvalidArgumentException("{$label} can hold at most {$max} ids.");
        }

        foreach ($ids as $id) {
            if (preg_match('/^\d{1,25}$/', $id) !== 1) {
                throw new InvalidArgumentException("{$label} must be Zendesk ids (numbers).");
            }
        }

        return array_map('intval', $ids);
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
     * A setting's text, or null when it is empty.
     *
     * @param  array<string, mixed>  $config
     */
    protected function text(array $config, string $key): ?string
    {
        return filled($config[$key] ?? null) ? trim((string) $config[$key]) : null;
    }

    /**
     * A checked email address.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function email(array $config, string $key = 'email'): string
    {
        $email = strtolower(trim((string) ($config[$key] ?? '')));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : throw new InvalidArgumentException($email === '' ? 'The email address is required.' : "\"{$email}\" is not an email address.");
    }

    /**
     * Tags from a list or a comma or space separated setting.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function tags(array $config, string $key = 'tags'): array
    {
        $value = $config[$key] ?? [];
        $parts = is_array($value) ? $value : preg_split('/[\s,]+/', (string) $value);

        return array_values(array_filter(array_map(fn (mixed $tag): string => trim((string) $tag), (array) $parts), fn (string $tag): bool => $tag !== ''));
    }

    /**
     * A JSON object setting; an empty map when not given.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function jsonObject(array $config, string $key, string $label): array
    {
        $value = $config[$key] ?? null;
        $decoded = is_string($value) ? (trim($value) === '' ? [] : json_decode($value, true)) : (array) $value;

        if (! is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new InvalidArgumentException("{$label} must be a JSON object.");
        }

        return $decoded;
    }

    /**
     * Drop what was left empty.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function present(array $values): array
    {
        return array_filter($values, fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    /**
     * How many records a list node reads: its `limit`, between 1 and `$max`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function limit(array $config, int $default, int $max = 1000): int
    {
        return max(1, min($max, (int) ($config['limit'] ?? $default)));
    }

    /**
     * The ticket fields a create or update node was given.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function ticketBody(array $config): array
    {
        $requester = $this->present(['email' => filled($config['requester_email'] ?? null) ? $this->email($config, 'requester_email') : null, 'name' => $this->text($config, 'requester_name')]);
        $comment = $this->text($config, 'comment');
        $custom = $this->jsonObject($config, 'custom_fields', 'The custom fields');

        return $this->present([
            'subject' => $this->text($config, 'subject'),
            'comment' => $comment !== null ? $this->commentBody($config, $comment) : null,
            'requester_id' => $this->optionalId($config, 'requester_id', 'The requester'),
            'requester' => isset($requester['email']) ? $requester : null,
            'assignee_id' => $this->optionalId($config, 'assignee_id', 'The assignee'),
            'group_id' => $this->optionalId($config, 'group_id', 'The group'),
            'organization_id' => $this->optionalId($config, 'organization_id', 'The organization'),
            'brand_id' => $this->optionalId($config, 'brand_id', 'The brand'),
            'ticket_form_id' => $this->optionalId($config, 'ticket_form_id', 'The ticket form'),
            'status' => $this->text($config, 'status'),
            'priority' => $this->text($config, 'priority'),
            'type' => $this->text($config, 'ticket_type'),
            'external_id' => $this->text($config, 'external_id'),
            'due_at' => filled($config['due_at'] ?? null) ? Carbon::parse((string) $config['due_at'])->utc()->format('Y-m-d\TH:i:s\Z') : null,
            'tags' => $this->tags($config) ?: null,
            'custom_fields' => array_map(fn (int|string $id, mixed $value): array => ['id' => (int) $id, 'value' => $value], array_keys($custom), $custom) ?: null,
        ]);
    }

    /**
     * A comment to send on a ticket: public unless asked otherwise, HTML when the body has tags.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function commentBody(array $config, string $text): array
    {
        return $this->present([
            'public' => filter_var($config['comment_public'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ($text !== strip_tags($text) ? 'html_body' : 'body') => $text,
            'author_id' => $this->optionalId($config, 'author_id', 'The author'),
            'uploads' => $this->tags($config, 'uploads') ?: null,
        ]);
    }

    /**
     * The person fields a create or update node was given.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function userBody(array $config): array
    {
        return $this->present([
            'name' => $this->text($config, 'name'),
            'email' => filled($config['email'] ?? null) ? $this->email($config) : null,
            'role' => $this->text($config, 'role'),
            'phone' => $this->text($config, 'phone'),
            'organization_id' => $this->optionalId($config, 'organization_id', 'The organization'),
            'external_id' => $this->text($config, 'external_id'),
            'tags' => $this->tags($config) ?: null,
            'notes' => $this->text($config, 'notes'),
            'details' => $this->text($config, 'details'),
            'user_fields' => $this->jsonObject($config, 'user_fields', 'The custom fields') ?: null,
        ]);
    }

    /**
     * The organization fields a create or update node was given.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function organizationBody(array $config): array
    {
        return $this->present([
            'name' => $this->text($config, 'name'),
            'external_id' => $this->text($config, 'external_id'),
            'domain_names' => $this->tags($config, 'domain_names') ?: null,
            'details' => $this->text($config, 'details'),
            'notes' => $this->text($config, 'notes'),
            'group_id' => $this->optionalId($config, 'group_id', 'The group'),
            'tags' => $this->tags($config) ?: null,
            'organization_fields' => $this->jsonObject($config, 'organization_fields', 'The custom fields') ?: null,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function organizationFields(): array
    {
        return [
            'external_id' => Field::advanced(Field::text('External id')),
            'domain_names' => Field::advanced(Field::text('Domains', 'Separated by commas.', 'acme.com')),
            'details' => Field::advanced(Field::textarea('Details')),
            'notes' => Field::advanced(Field::textarea('Notes')),
            'group_id' => Field::advanced($this->groupField()),
            'tags' => Field::advanced(Field::text('Tags', 'Separated by commas. On an update this replaces the tags.')),
            'organization_fields' => Field::advanced(Field::json('Custom fields (JSON)', 'By field key, e.g. {"plan": "gold"}.')),
        ];
    }

    /**
     * Add, remove or replace the tags of a ticket, person or organization. Zendesk's
     * method says which: PUT adds, DELETE removes, POST replaces.
     *
     * @param  array<string, mixed>  $config
     * @return array{tags: list<string>, id: int}
     *
     * @throws InvalidArgumentException
     */
    protected function changeTags(Run $run, array $config, string $method): array
    {
        $resource = $config['resource'] ?? 'tickets';

        if (! in_array($resource, ['tickets', 'users', 'organizations'], true)) {
            throw new InvalidArgumentException('Tags go on a ticket, a person or an organization.');
        }

        $id = $this->numericId($config, 'resource_id', 'The id');
        $tags = $this->tags($config);

        if ($tags === []) {
            throw new InvalidArgumentException('At least one tag is required.');
        }

        $answer = $this->zendesk($run, $config, $method, "/{$resource}/{$id}/tags", [], ['tags' => $tags]);

        return ['tags' => array_values((array) ($answer['tags'] ?? [])), 'id' => (int) $id];
    }

    /**
     * Fetch the file at the setting's address and hand it to Zendesk.
     *
     * @param  array<string, mixed>  $config
     * @return array{token: mixed, attachment: mixed}
     *
     * @throws RuntimeException
     */
    protected function sendUpload(Run $run, array $config): array
    {
        $url = $this->requiredString($config, 'file_url', 'The file address');
        $name = $this->text($config, 'filename') ?? (basename((string) parse_url($url, PHP_URL_PATH)) ?: 'file');
        $file = (new GuardedHttp(new SsrfGuard))->send('GET', $url, [], null, timeoutSeconds: 60);

        if (! $file->successful()) {
            throw new RuntimeException("Could not fetch the file (HTTP {$file->status()}).");
        }

        $data = $this->resolveCredentialData($run, $config);
        $response = self::client($data)
            ->withBody($file->body(), $file->header('Content-Type') ?: 'application/binary')
            ->post(self::apiRoot((string) ($data['subdomain'] ?? '')).'/uploads?'.http_build_query(['filename' => $name]));

        $this->assertOk($response);

        return ['token' => $response->json('upload.token'), 'attachment' => $response->json('upload.attachment')];
    }

    /**
     * A side conversation message: subject, body and who it goes to.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function sideMessage(array $config): array
    {
        $to = array_map(fn (string $address): array => ['email' => $this->email(['to' => $address], 'to')], $this->tags($config, 'to'));

        if ($to === []) {
            throw new InvalidArgumentException('Say who the message goes to.');
        }

        return $this->present(['subject' => $this->text($config, 'subject'), 'body' => $this->requiredString($config, 'body', 'The message'), 'to' => $to]);
    }

    /**
     * Zendesk Talk's call or leg export since a time, at most `limit` records. The
     * export always names a next page, so an empty page ends it.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function talkExport(Run $run, array $config, string $key): array
    {
        $since = filled($config['since'] ?? null) ? Carbon::parse((string) $config['since']) : Carbon::now()->subDay();
        $start = min($since->getTimestamp(), Carbon::now()->subMinutes(2)->getTimestamp());
        $rows = $this->listAll($run, $config, "/channels/voice/stats/incremental/{$key}", $key, ['start_time' => $start, 'page' => null], $this->limit($config, 100));

        return [$key => $rows, 'count' => count($rows)];
    }

    /**
     * The effect of a free-form call, from its method.
     *
     * @param  array<string, mixed>  $config
     */
    protected function effectOfMethod(array $config): ActionEffect
    {
        return match (strtoupper((string) ($config['method'] ?? 'GET'))) {
            'GET' => ActionEffect::Read,
            'DELETE' => ActionEffect::Destructive,
            default => ActionEffect::Write,
        };
    }

    /**
     * @param  array<string, mixed>  $ticket
     * @return array<string, mixed>
     */
    protected function shapeTicket(array $ticket): array
    {
        return [
            'id' => $ticket['id'] ?? null,
            'subject' => $ticket['subject'] ?? null,
            'description' => $ticket['description'] ?? null,
            'status' => $ticket['status'] ?? null,
            'priority' => $ticket['priority'] ?? null,
            'type' => $ticket['type'] ?? null,
            'requester_id' => $ticket['requester_id'] ?? null,
            'assignee_id' => $ticket['assignee_id'] ?? null,
            'group_id' => $ticket['group_id'] ?? null,
            'organization_id' => $ticket['organization_id'] ?? null,
            'brand_id' => $ticket['brand_id'] ?? null,
            'external_id' => $ticket['external_id'] ?? null,
            'tags' => $ticket['tags'] ?? [],
            'custom_fields' => $ticket['custom_fields'] ?? [],
            'due_at' => $ticket['due_at'] ?? null,
            'created_at' => $ticket['created_at'] ?? null,
            'updated_at' => $ticket['updated_at'] ?? null,
            'url' => $ticket['url'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>
     */
    protected function shapeUser(array $user): array
    {
        return [
            'id' => $user['id'] ?? null,
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
            'phone' => $user['phone'] ?? null,
            'role' => $user['role'] ?? null,
            'organization_id' => $user['organization_id'] ?? null,
            'external_id' => $user['external_id'] ?? null,
            'active' => $user['active'] ?? null,
            'suspended' => $user['suspended'] ?? null,
            'tags' => $user['tags'] ?? [],
            'user_fields' => $user['user_fields'] ?? [],
            'created_at' => $user['created_at'] ?? null,
            'updated_at' => $user['updated_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $organization
     * @return array<string, mixed>
     */
    protected function shapeOrganization(array $organization): array
    {
        return [
            'id' => $organization['id'] ?? null,
            'name' => $organization['name'] ?? null,
            'external_id' => $organization['external_id'] ?? null,
            'domain_names' => $organization['domain_names'] ?? [],
            'details' => $organization['details'] ?? null,
            'notes' => $organization['notes'] ?? null,
            'group_id' => $organization['group_id'] ?? null,
            'tags' => $organization['tags'] ?? [],
            'organization_fields' => $organization['organization_fields'] ?? [],
            'created_at' => $organization['created_at'] ?? null,
            'updated_at' => $organization['updated_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $comment
     * @return array<string, mixed>
     */
    protected function shapeComment(array $comment): array
    {
        return [
            'id' => $comment['id'] ?? null,
            'type' => $comment['type'] ?? null,
            'author_id' => $comment['author_id'] ?? null,
            'body' => $comment['body'] ?? null,
            'html_body' => $comment['html_body'] ?? null,
            'public' => $comment['public'] ?? null,
            'attachments' => $comment['attachments'] ?? [],
            'created_at' => $comment['created_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    protected function shapeGroup(array $group): array
    {
        return ['id' => $group['id'] ?? null, 'name' => $group['name'] ?? null, 'description' => $group['description'] ?? null, 'is_public' => $group['is_public'] ?? null, 'created_at' => $group['created_at'] ?? null, 'updated_at' => $group['updated_at'] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    protected function groupField(string $title = 'Group'): array
    {
        return Field::dynamic($title, 'zendesk.groups', null, 'Support');
    }

    /**
     * @return array<string, mixed>
     */
    protected function agentField(string $title = 'Assignee'): array
    {
        return Field::dynamic($title, 'zendesk.agents', null, 'Ada Lovelace');
    }

    /**
     * The settings every ticket create and update node shares.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function ticketFields(): array
    {
        return [
            'subject' => Field::text('Subject', null, 'Cannot log in'),
            'comment' => Field::textarea('Comment', 'The first comment on a new ticket, or a new comment on an existing one. HTML is kept.'),
            'comment_public' => Field::advanced(Field::boolean('Public comment', 'Off makes it an internal note.', true)),
            'requester_email' => Field::text('Requester email', 'Zendesk creates the person if they are new.', 'ada@example.com'),
            'requester_name' => Field::advanced(Field::text('Requester name', 'Used when the requester is new.')),
            'requester_id' => Field::advanced(Field::text('Requester id', 'Instead of the email.')),
            'assignee_id' => Field::advanced($this->agentField()),
            'group_id' => Field::advanced($this->groupField()),
            'organization_id' => Field::advanced(Field::dynamic('Organization', 'zendesk.organizations', null, 'Acme')),
            'status' => Field::advanced(Field::select('Status', ['new' => 'New', 'open' => 'Open', 'pending' => 'Pending', 'hold' => 'On hold', 'solved' => 'Solved', 'closed' => 'Closed'])),
            'priority' => Field::advanced(Field::select('Priority', ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'])),
            'ticket_type' => Field::advanced(Field::select('Type', ['question' => 'Question', 'incident' => 'Incident', 'problem' => 'Problem', 'task' => 'Task'])),
            'tags' => Field::advanced(Field::text('Tags', 'Separated by commas. On an update this replaces the tags.', 'vip, billing')),
            'due_at' => Field::advanced(Field::datetime('Due', 'For tasks.')),
            'ticket_form_id' => Field::advanced(Field::dynamic('Ticket form', 'zendesk.ticket_forms')),
            'brand_id' => Field::advanced(Field::dynamic('Brand', 'zendesk.brands')),
            'external_id' => Field::advanced(Field::text('External id')),
            'custom_fields' => Field::advanced(Field::json('Custom fields (JSON)', 'By field id, e.g. {"360001234": "Gold"}.')),
            'uploads' => Field::advanced(Field::text('Upload tokens', 'From Upload Attachment, separated by commas. Needs a comment.')),
        ];
    }
}
