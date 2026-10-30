<?php

namespace App\Nodes\Integrations\Intercom;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the Intercom
 * node family. Intercom is called with an access token on the workspace's region,
 * with the API version in a header. Ids are text. A list pages with `starting_after`
 * or `page`; a search takes a filter tree and answers `{data: [...]}` or, for
 * conversations and tickets, the plural name.
 */
abstract class AbstractIntercomNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    public const string API_VERSION = '2.17';

    public function category(): string
    {
        return 'intercom';
    }

    /**
     * The API address of the workspace's region.
     *
     * @param  array<string, mixed>  $data
     */
    public static function apiRoot(array $data): string
    {
        return match (strtolower(trim((string) ($data['region'] ?? '')))) {
            'eu' => 'https://api.eu.intercom.io',
            'au' => 'https://api.au.intercom.io',
            default => 'https://api.intercom.io',
        };
    }

    /**
     * An authorised client for the connection's fields.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public static function client(array $data): PendingRequest
    {
        $token = trim((string) ($data['api_key'] ?? ''));

        if ($token === '') {
            throw new InvalidArgumentException('Add the Intercom access token to the connection.');
        }

        return Http::withToken($token)->withHeaders(['Intercom-Version' => self::API_VERSION])->acceptJson()->timeout(30);
    }

    /**
     * Call Intercom and return the decoded body (an empty array for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function intercom(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->intercomResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Call Intercom and return the response as it is, for a node that treats a 404 as an answer.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function intercomResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $data = $this->resolveCredentialData($run, $config);
        $options = ['query' => array_filter($query, fn (mixed $value): bool => $value !== null)];

        if ($body !== null) {
            $options['json'] = $body === [] ? new stdClass : $body;
        }

        return self::client($data)->send(strtoupper($method), self::apiRoot($data).$path, $options);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = (string) ($response->json('errors.0.message') ?? $response->json('message') ?? "HTTP {$response->status()}");
        $code = $response->json('errors.0.code');
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Intercom: the access token was refused.',
            $response->status() === 403 => ' The Intercom token lacks the permission for that.',
            $response->status() === 429 => ' Intercom is rate limiting the workspace; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Intercom error: '.mb_substr($message, 0, 400).($code ? " ({$code})" : '').(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Read a cursor list page by page until `$limit` records are in. A search is
     * posted with its query; a plain list is read with GET.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $search  the search query to post, or null for a GET list
     * @return list<array<string, mixed>>
     */
    protected function cursorAll(Run $run, array $config, string $path, string $key, array $query, ?array $search, int $limit, ?array $sort = null): array
    {
        $records = [];
        $after = null;

        do {
            $perPage = min(150, max(1, $limit - count($records)));

            if ($search !== null) {
                $body = ['query' => $search, 'pagination' => array_filter(['per_page' => $perPage, 'starting_after' => $after])];
                $answer = $this->intercom($run, $config, 'POST', $path, [], $sort !== null ? [...$body, 'sort' => $sort] : $body);
            } else {
                $answer = $this->intercom($run, $config, 'GET', $path, [...$query, 'per_page' => $perPage, 'starting_after' => $after]);
            }

            $rows = array_values(array_filter((array) data_get($answer, $key, []), 'is_array'));
            array_push($records, ...$rows);
            $after = data_get($answer, 'pages.next.starting_after');
        } while (filled($after) && $rows !== [] && count($records) < $limit);

        return array_slice($records, 0, $limit);
    }

    /**
     * Read a numbered list page by page until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function pagedAll(Run $run, array $config, string $path, string $key, array $query, int $limit, string $method = 'GET'): array
    {
        $records = [];
        $page = 1;

        do {
            $answer = $this->intercom($run, $config, $method, $path, [...$query, 'page' => $page, 'per_page' => 50]);
            $rows = array_values(array_filter((array) data_get($answer, $key, []), 'is_array'));
            array_push($records, ...$rows);
            $page++;
            $more = $rows !== [] && (int) data_get($answer, 'pages.total_pages', 1) >= $page;
        } while ($more && count($records) < $limit);

        return array_slice($records, 0, $limit);
    }

    /**
     * Read one record, or say it was not found.
     *
     * @param  array<string, mixed>  $config
     * @param  (callable(array<string, mixed>): array<string, mixed>)|null  $shape
     * @return array<string, mixed>
     */
    protected function findOne(Run $run, array $config, string $path, string $id, ?callable $shape = null): array
    {
        $response = $this->intercomResponse($run, $config, 'GET', $path);

        if ($response->status() === 404) {
            return ['found' => false, 'id' => $id];
        }

        $this->assertOk($response);
        $record = (array) ($response->json() ?? []);

        return ['found' => true, ...($shape === null ? $record : $shape($record))];
    }

    /**
     * A search filter tree from simple filters: one stays itself, several are ANDed.
     *
     * @param  list<array{field: string, operator: string, value: mixed}>  $filters
     * @return array<string, mixed>
     */
    protected function allOf(array $filters): array
    {
        return count($filters) === 1 ? $filters[0] : ['operator' => 'AND', 'value' => $filters];
    }

    /**
     * An id from a setting, checked so it can sit in a URL.
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
     * A boolean setting, or null when it was left out.
     *
     * @param  array<string, mixed>  $config
     */
    protected function flag(array $config, string $key): ?bool
    {
        return isset($config[$key]) && $config[$key] !== '' ? filter_var($config[$key], FILTER_VALIDATE_BOOLEAN) : null;
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
     * The teammate an action is done as: the setting, or the token's own teammate.
     *
     * @param  array<string, mixed>  $config
     */
    protected function adminId(Run $run, array $config): string
    {
        if (filled($config['admin_id'] ?? null)) {
            return $this->safeId($config, 'admin_id', 'The teammate');
        }

        $admin = (string) ($this->intercom($run, $config, 'GET', '/me')['id'] ?? '');

        return $admin !== '' ? $admin : throw new RuntimeException('Intercom did not say which teammate this token belongs to. Choose a teammate.');
    }

    /**
     * The contact fields a create or update node was given.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function contactBody(array $config): array
    {
        return $this->present([
            'role' => $this->text($config, 'role'),
            'external_id' => $this->text($config, 'external_id'),
            'email' => filled($config['email'] ?? null) ? $this->email($config) : null,
            'phone' => $this->text($config, 'phone'),
            'name' => $this->text($config, 'name'),
            'owner_id' => $this->text($config, 'owner_id'),
            'unsubscribed_from_emails' => $this->flag($config, 'unsubscribed_from_emails'),
            'custom_attributes' => $this->jsonObject($config, 'custom_attributes', 'The custom attributes') ?: null,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function contactFields(): array
    {
        return [
            'email' => Field::text('Email', null, 'ada@example.com'),
            'name' => Field::text('Name'),
            'role' => Field::advanced(Field::select('Role', ['user' => 'User', 'lead' => 'Lead'])),
            'external_id' => Field::advanced(Field::text('External id', 'Your own id for this person.')),
            'phone' => Field::advanced(Field::text('Phone')),
            'owner_id' => Field::advanced(Field::dynamic('Owner', 'intercom.admins')),
            'unsubscribed_from_emails' => Field::advanced(Field::boolean('Unsubscribed from email')),
            'custom_attributes' => Field::advanced(Field::json('Custom attributes (JSON)', 'e.g. {"plan": "gold"}.')),
        ];
    }

    /**
     * The company fields a create or update node was given.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function companyBody(array $config): array
    {
        return $this->present([
            'name' => $this->text($config, 'name'),
            'plan' => $this->text($config, 'plan'),
            'size' => filled($config['size'] ?? null) ? (int) $config['size'] : null,
            'website' => $this->text($config, 'website'),
            'industry' => $this->text($config, 'industry'),
            'monthly_spend' => filled($config['monthly_spend'] ?? null) ? (int) $config['monthly_spend'] : null,
            'custom_attributes' => $this->jsonObject($config, 'custom_attributes', 'The custom attributes') ?: null,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function companyFields(): array
    {
        return [
            'name' => Field::text('Name'),
            'plan' => Field::advanced(Field::text('Plan')),
            'size' => Field::advanced(Field::integer('Employees')),
            'website' => Field::advanced(Field::text('Website')),
            'industry' => Field::advanced(Field::text('Industry')),
            'monthly_spend' => Field::advanced(Field::integer('Monthly spend')),
            'custom_attributes' => Field::advanced(Field::json('Custom attributes (JSON)', 'e.g. {"tier": "gold"}.')),
        ];
    }

    /**
     * A conversation part made by a teammate: closing, opening, snoozing or assigning.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function managePart(Run $run, array $config, string $messageType, array $fields = []): array
    {
        $id = $this->safeId($config, 'conversation_id', 'The conversation');

        return $this->shapeConversation($this->intercom($run, $config, 'POST', "/conversations/{$id}/parts", [], ['message_type' => $messageType, 'type' => 'admin', 'admin_id' => $this->adminId($run, $config), ...$fields]));
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
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    protected function shapeContact(array $contact): array
    {
        return [
            'id' => $contact['id'] ?? null,
            'role' => $contact['role'] ?? null,
            'external_id' => $contact['external_id'] ?? null,
            'email' => $contact['email'] ?? null,
            'phone' => $contact['phone'] ?? null,
            'name' => $contact['name'] ?? null,
            'owner_id' => $contact['owner_id'] ?? null,
            'unsubscribed_from_emails' => $contact['unsubscribed_from_emails'] ?? null,
            'custom_attributes' => $contact['custom_attributes'] ?? [],
            'created_at' => $contact['created_at'] ?? null,
            'updated_at' => $contact['updated_at'] ?? null,
            'signed_up_at' => $contact['signed_up_at'] ?? null,
            'last_seen_at' => $contact['last_seen_at'] ?? null,
            'last_replied_at' => $contact['last_replied_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $company
     * @return array<string, mixed>
     */
    protected function shapeCompany(array $company): array
    {
        return [
            'id' => $company['id'] ?? null,
            'company_id' => $company['company_id'] ?? null,
            'name' => $company['name'] ?? null,
            'plan' => data_get($company, 'plan.name'),
            'size' => $company['size'] ?? null,
            'website' => $company['website'] ?? null,
            'industry' => $company['industry'] ?? null,
            'monthly_spend' => $company['monthly_spend'] ?? null,
            'user_count' => $company['user_count'] ?? null,
            'custom_attributes' => $company['custom_attributes'] ?? [],
            'created_at' => $company['created_at'] ?? null,
            'updated_at' => $company['updated_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $conversation
     * @return array<string, mixed>
     */
    protected function shapeConversation(array $conversation): array
    {
        return [
            'id' => isset($conversation['id']) ? (string) $conversation['id'] : null,
            'title' => $conversation['title'] ?? null,
            'state' => $conversation['state'] ?? null,
            'open' => $conversation['open'] ?? null,
            'read' => $conversation['read'] ?? null,
            'priority' => $conversation['priority'] ?? null,
            'admin_assignee_id' => $conversation['admin_assignee_id'] ?? null,
            'team_assignee_id' => $conversation['team_assignee_id'] ?? null,
            'contact_ids' => array_values(array_filter(array_map(fn (mixed $contact): mixed => data_get($contact, 'id'), (array) data_get($conversation, 'contacts.contacts', [])))),
            'tags' => array_values(array_filter(array_map(fn (mixed $tag): mixed => data_get($tag, 'name'), (array) data_get($conversation, 'tags.tags', [])))),
            'body' => data_get($conversation, 'source.body'),
            'subject' => data_get($conversation, 'source.subject'),
            'rating' => data_get($conversation, 'conversation_rating.rating'),
            'custom_attributes' => $conversation['custom_attributes'] ?? [],
            'created_at' => $conversation['created_at'] ?? null,
            'updated_at' => $conversation['updated_at'] ?? null,
            'last_contact_reply_at' => data_get($conversation, 'statistics.last_contact_reply_at'),
            'last_admin_reply_at' => data_get($conversation, 'statistics.last_admin_reply_at'),
            'last_close_at' => data_get($conversation, 'statistics.last_close_at'),
            'rated_at' => data_get($conversation, 'conversation_rating.created_at'),
            'conversation_parts' => data_get($conversation, 'conversation_parts.conversation_parts'),
        ];
    }

    /**
     * @param  array<string, mixed>  $ticket
     * @return array<string, mixed>
     */
    protected function shapeTicket(array $ticket): array
    {
        return [
            'id' => isset($ticket['id']) ? (string) $ticket['id'] : null,
            'ticket_id' => $ticket['ticket_id'] ?? null,
            'category' => $ticket['category'] ?? null,
            'attributes' => $ticket['ticket_attributes'] ?? [],
            'state' => data_get($ticket, 'ticket_state.internal_label') ?? data_get($ticket, 'ticket_state.category'),
            'state_id' => data_get($ticket, 'ticket_state.id'),
            'type' => data_get($ticket, 'ticket_type.name'),
            'type_id' => data_get($ticket, 'ticket_type.id'),
            'open' => $ticket['open'] ?? null,
            'admin_assignee_id' => $ticket['admin_assignee_id'] ?? null,
            'team_assignee_id' => $ticket['team_assignee_id'] ?? null,
            'company_id' => $ticket['company_id'] ?? null,
            'contact_ids' => array_values(array_filter(array_map(fn (mixed $contact): mixed => data_get($contact, 'id'), (array) data_get($ticket, 'contacts.contacts', [])))),
            'created_at' => $ticket['created_at'] ?? null,
            'updated_at' => $ticket['updated_at'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function adminField(string $title = 'As teammate'): array
    {
        return Field::advanced(Field::dynamic($title, 'intercom.admins', 'Empty uses the teammate this token belongs to.', 'Ada Lovelace'));
    }
}
