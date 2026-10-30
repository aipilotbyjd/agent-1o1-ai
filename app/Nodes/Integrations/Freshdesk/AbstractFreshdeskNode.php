<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the Freshdesk
 * node family. Freshdesk is called at the account's own subdomain with the API key
 * as the basic auth user. Ids are numbers. A list pages with `page` and `per_page`
 * (at most 100) and answers a plain JSON list; a search answers `{results, total}`,
 * ten pages of thirty at most.
 */
abstract class AbstractFreshdeskNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    public function category(): string
    {
        return 'freshdesk';
    }

    /**
     * The API root of a subdomain, as typed or pasted: `acme`, `acme.freshdesk.com` or a full address.
     *
     * @throws InvalidArgumentException
     */
    public static function apiRoot(string $subdomain): string
    {
        $host = preg_replace('~^https?://~i', '', strtolower(trim($subdomain))) ?? '';
        $host = explode('/', $host)[0];
        $host = str_ends_with($host, '.freshdesk.com') ? substr($host, 0, -strlen('.freshdesk.com')) : $host;

        if (preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $host) !== 1) {
            throw new InvalidArgumentException('Enter the Freshdesk subdomain, like acme for acme.freshdesk.com.');
        }

        return "https://{$host}.freshdesk.com/api/v2";
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
        $key = trim((string) ($data['api_key'] ?? ''));

        if ($key === '') {
            throw new InvalidArgumentException('Add the Freshdesk API key to the connection.');
        }

        return Http::withBasicAuth($key, 'X')->acceptJson()->timeout(30);
    }

    /**
     * Call Freshdesk and return the decoded body (an empty array for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  null values are dropped
     * @param  array<string, mixed>|null  $body
     * @return array<mixed>
     *
     * @throws RuntimeException
     */
    protected function freshdesk(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->freshdeskResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * Call Freshdesk and return the response as it is, for a node that treats a 404 as an answer.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function freshdeskResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
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

        $message = (string) ($response->json('description') ?? $response->json('message') ?? "HTTP {$response->status()}");
        $errors = collect((array) $response->json('errors'))->filter(fn (mixed $error): bool => is_array($error))
            ->map(fn (array $error): string => trim(($error['field'] ?? '').': '.($error['message'] ?? ''), ': '))->implode('; ');
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Freshdesk: the API key was refused.',
            $response->status() === 403 => ' The Freshdesk user or plan is not allowed to do that.',
            $response->status() === 429 => ' Freshdesk is rate limiting the account; try again in a moment.',
            default => '',
        };

        throw new RuntimeException('Freshdesk error: '.mb_substr($message.($errors !== '' ? " {$errors}" : ''), 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * Read a list page by page until `$limit` records are in.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function listAll(Run $run, array $config, string $path, array $query, int $limit, ?string $key = null): array
    {
        $records = [];
        $page = 1;

        do {
            $want = min(100, $limit - count($records));
            $answer = $this->freshdesk($run, $config, 'GET', $path, [...$query, 'page' => $page, 'per_page' => 100]);
            $rows = array_values(array_filter((array) ($key === null ? $answer : data_get($answer, $key, [])), 'is_array'));
            array_push($records, ...$rows);
            $page++;
        } while (count($rows) === 100 && $want > 0 && count($records) < $limit);

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
    protected function listing(Run $run, array $config, string $path, string $key, array $query = [], ?callable $shape = null, ?string $answerKey = null): array
    {
        $rows = $this->listAll($run, $config, $path, $query, $this->limit($config, 100), $answerKey);

        return [$key => $shape === null ? $rows : array_map($shape, $rows), 'count' => count($rows)];
    }

    /**
     * Run a filter search, ten pages of thirty at most.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    protected function searchAll(Run $run, array $config, string $path, string $query, int $limit): array
    {
        $query = trim($query, ' "');

        if ($query === '' || mb_strlen($query) > 510) {
            throw new InvalidArgumentException('The search is required and can be at most 510 characters.');
        }

        $records = [];
        $page = 1;

        do {
            $answer = $this->freshdesk($run, $config, 'GET', $path, ['query' => '"'.$query.'"', 'page' => $page]);
            $rows = array_values(array_filter((array) ($answer['results'] ?? []), 'is_array'));
            array_push($records, ...$rows);
            $page++;
        } while ($rows !== [] && count($rows) === 30 && $page <= 10 && count($records) < $limit);

        return array_slice($records, 0, $limit);
    }

    /**
     * Read one record, or say it was not found.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  (callable(array<string, mixed>): array<string, mixed>)|null  $shape
     * @return array<string, mixed>
     */
    protected function findOne(Run $run, array $config, string $path, string $id, ?callable $shape = null, array $query = []): array
    {
        $response = $this->freshdeskResponse($run, $config, 'GET', $path, $query);

        if ($response->status() === 404) {
            return ['found' => false, 'id' => $id];
        }

        $this->assertOk($response);
        $record = (array) ($response->json() ?? []);

        return ['found' => true, ...($shape === null ? $record : $shape($record))];
    }

    /**
     * Delete something and say so.
     *
     * @param  array<string, mixed>  $config
     * @return array{deleted: true, id: string}
     */
    protected function destroy(Run $run, array $config, string $path, string $id): array
    {
        $this->freshdesk($run, $config, 'DELETE', $path);

        return ['deleted' => true, 'id' => $id];
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

        return preg_match('/^\d{1,25}$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} must be a Freshdesk id (a number).");
    }

    /**
     * A text id that sits in a URL, such as a custom object record's or a message's.
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
     * Several numeric ids from a list or a comma separated setting.
     *
     * @param  array<string, mixed>  $config
     * @return list<int>
     *
     * @throws InvalidArgumentException
     */
    protected function idList(array $config, string $key, string $label, bool $required = true): array
    {
        $value = $config[$key] ?? [];
        $parts = is_array($value) ? $value : explode(',', (string) $value);
        $ids = array_values(array_filter(array_map(fn (mixed $part): string => trim((string) $part), $parts), fn (string $part): bool => $part !== ''));

        if ($ids === [] && $required) {
            throw new InvalidArgumentException("{$label} is required.");
        }

        foreach ($ids as $id) {
            if (preg_match('/^\d{1,25}$/', $id) !== 1) {
                throw new InvalidArgumentException("{$label} must be Freshdesk ids (numbers).");
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
     * Words from a list or a comma separated setting: tags, addresses, ids of text.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function words(array $config, string $key): array
    {
        $value = $config[$key] ?? [];
        $parts = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(fn (mixed $word): string => trim((string) $word), $parts), fn (string $word): bool => $word !== ''));
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
     * A number setting, or null when it was left out.
     *
     * @param  array<string, mixed>  $config
     */
    protected function number(array $config, string $key): ?int
    {
        return is_numeric($config[$key] ?? null) ? (int) $config[$key] : null;
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
        $time = fn (string $key): ?string => filled($config[$key] ?? null) ? Carbon::parse((string) $config[$key])->utc()->format('Y-m-d\TH:i:s\Z') : null;

        return $this->present([
            'subject' => $this->text($config, 'subject'),
            'description' => $this->text($config, 'description'),
            'email' => filled($config['email'] ?? null) ? $this->email($config) : null,
            'name' => $this->text($config, 'name'),
            'requester_id' => $this->optionalId($config, 'requester_id', 'The requester'),
            'phone' => $this->text($config, 'phone'),
            'status' => $this->number($config, 'status'),
            'priority' => $this->number($config, 'priority'),
            'source' => $this->number($config, 'source'),
            'type' => $this->text($config, 'type'),
            'group_id' => $this->optionalId($config, 'group_id', 'The group'),
            'responder_id' => $this->optionalId($config, 'responder_id', 'The agent'),
            'company_id' => $this->optionalId($config, 'company_id', 'The company'),
            'product_id' => $this->optionalId($config, 'product_id', 'The product'),
            'email_config_id' => $this->optionalId($config, 'email_config_id', 'The sending address'),
            'due_by' => $time('due_by'),
            'fr_due_by' => $time('fr_due_by'),
            'tags' => $this->words($config, 'tags') ?: null,
            'cc_emails' => $this->words($config, 'cc_emails') ?: null,
            'custom_fields' => $this->jsonObject($config, 'custom_fields', 'The custom fields') ?: null,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function ticketFields(): array
    {
        return [
            'subject' => Field::text('Subject', null, 'Cannot log in'),
            'description' => Field::textarea('Description', 'HTML is kept.'),
            'email' => Field::text('Requester email', 'Freshdesk creates the contact if they are new.', 'ada@example.com'),
            'name' => Field::advanced(Field::text('Requester name')),
            'requester_id' => Field::advanced(Field::text('Requester id', 'Instead of the email.')),
            'phone' => Field::advanced(Field::text('Requester phone')),
            'status' => Field::advanced(Field::select('Status', self::STATUSES)),
            'priority' => Field::advanced(Field::select('Priority', self::PRIORITIES)),
            'source' => Field::advanced(Field::select('Source', ['1' => 'Email', '2' => 'Portal', '3' => 'Phone', '7' => 'Chat', '9' => 'Feedback widget', '10' => 'Outbound email'])),
            'type' => Field::advanced(Field::text('Type', null, 'Question')),
            'group_id' => Field::advanced($this->groupField()),
            'responder_id' => Field::advanced($this->agentField()),
            'company_id' => Field::advanced(Field::dynamic('Company', 'freshdesk.companies')),
            'product_id' => Field::advanced(Field::dynamic('Product', 'freshdesk.products')),
            'email_config_id' => Field::advanced(Field::dynamic('Sending address', 'freshdesk.email_configs')),
            'due_by' => Field::advanced(Field::datetime('Resolution due')),
            'fr_due_by' => Field::advanced(Field::datetime('First response due')),
            'tags' => Field::advanced(Field::text('Tags', 'Separated by commas. On an update this replaces the tags.')),
            'cc_emails' => Field::advanced(Field::emails('CC')),
            'custom_fields' => Field::advanced(Field::json('Custom fields (JSON)', 'By API name, e.g. {"cf_plan": "Gold"}.')),
        ];
    }

    /**
     * @var array<string, string>
     */
    protected const array STATUSES = ['2' => 'Open', '3' => 'Pending', '4' => 'Resolved', '5' => 'Closed'];

    /**
     * @var array<string, string>
     */
    protected const array PRIORITIES = ['1' => 'Low', '2' => 'Medium', '3' => 'High', '4' => 'Urgent'];

    /**
     * Change one thing on a ticket and answer with the ticket.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $change
     * @return array<string, mixed>
     */
    protected function changeTicket(Run $run, array $config, array $change): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');

        return $this->shapeTicket($this->freshdesk($run, $config, 'PUT', "/tickets/{$id}", [], $change));
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
            'name' => $this->text($config, 'name'),
            'email' => filled($config['email'] ?? null) ? $this->email($config) : null,
            'phone' => $this->text($config, 'phone'),
            'mobile' => $this->text($config, 'mobile'),
            'twitter_id' => $this->text($config, 'twitter_id'),
            'unique_external_id' => $this->text($config, 'unique_external_id'),
            'company_id' => $this->optionalId($config, 'company_id', 'The company'),
            'job_title' => $this->text($config, 'job_title'),
            'address' => $this->text($config, 'address'),
            'description' => $this->text($config, 'description'),
            'language' => $this->text($config, 'language'),
            'time_zone' => $this->text($config, 'time_zone'),
            'other_emails' => $this->words($config, 'other_emails') ?: null,
            'tags' => $this->words($config, 'tags') ?: null,
            'custom_fields' => $this->jsonObject($config, 'custom_fields', 'The custom fields') ?: null,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function contactFields(): array
    {
        return [
            'name' => Field::text('Name'),
            'email' => Field::text('Email', null, 'ada@example.com'),
            'phone' => Field::advanced(Field::text('Phone')),
            'mobile' => Field::advanced(Field::text('Mobile')),
            'twitter_id' => Field::advanced(Field::text('Twitter handle')),
            'unique_external_id' => Field::advanced(Field::text('External id')),
            'company_id' => Field::advanced(Field::dynamic('Company', 'freshdesk.companies')),
            'job_title' => Field::advanced(Field::text('Job title')),
            'address' => Field::advanced(Field::text('Address')),
            'description' => Field::advanced(Field::textarea('About')),
            'language' => Field::advanced(Field::text('Language', null, 'en')),
            'time_zone' => Field::advanced(Field::text('Time zone')),
            'other_emails' => Field::advanced(Field::emails('Other emails')),
            'tags' => Field::advanced(Field::text('Tags', 'Separated by commas.')),
            'custom_fields' => Field::advanced(Field::json('Custom fields (JSON)', 'By API name, e.g. {"cf_plan": "Gold"}.')),
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
            'description' => $this->text($config, 'description'),
            'note' => $this->text($config, 'note'),
            'domains' => $this->words($config, 'domains') ?: null,
            'health_score' => $this->text($config, 'health_score'),
            'account_tier' => $this->text($config, 'account_tier'),
            'industry' => $this->text($config, 'industry'),
            'custom_fields' => $this->jsonObject($config, 'custom_fields', 'The custom fields') ?: null,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function companyFields(): array
    {
        return [
            'name' => Field::text('Name'),
            'description' => Field::advanced(Field::textarea('Description')),
            'note' => Field::advanced(Field::textarea('Note')),
            'domains' => Field::advanced(Field::text('Domains', 'Separated by commas.', 'acme.com')),
            'health_score' => Field::advanced(Field::text('Health score')),
            'account_tier' => Field::advanced(Field::text('Account tier')),
            'industry' => Field::advanced(Field::text('Industry')),
            'custom_fields' => Field::advanced(Field::json('Custom fields (JSON)', 'By API name, e.g. {"cf_plan": "Gold"}.')),
        ];
    }

    /**
     * A free-form body from a JSON setting, for records too varied for fields.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function requiredJson(array $config, string $key, string $label): array
    {
        $body = $this->jsonObject($config, $key, $label);

        return $body !== [] ? $body : throw new InvalidArgumentException("{$label} is required.");
    }

    /**
     * Every attachment on a ticket: the description's and each conversation's.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    protected function ticketAttachments(Run $run, array $config): array
    {
        $id = $this->numericId($config, 'ticket_id', 'The ticket');
        $ticket = $this->freshdesk($run, $config, 'GET', "/tickets/{$id}");
        $attachments = array_map(fn (mixed $file): array => [...(array) $file, 'conversation_id' => null], (array) ($ticket['attachments'] ?? []));

        foreach ($this->listAll($run, $config, "/tickets/{$id}/conversations", [], 1000) as $conversation) {
            foreach ((array) ($conversation['attachments'] ?? []) as $file) {
                $attachments[] = [...(array) $file, 'conversation_id' => $conversation['id'] ?? null];
            }
        }

        return $attachments;
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
            'description' => $ticket['description_text'] ?? $ticket['description'] ?? null,
            'status' => $ticket['status'] ?? null,
            'status_name' => self::STATUSES[(string) ($ticket['status'] ?? '')] ?? null,
            'priority' => $ticket['priority'] ?? null,
            'priority_name' => self::PRIORITIES[(string) ($ticket['priority'] ?? '')] ?? null,
            'source' => $ticket['source'] ?? null,
            'type' => $ticket['type'] ?? null,
            'requester_id' => $ticket['requester_id'] ?? null,
            'responder_id' => $ticket['responder_id'] ?? null,
            'group_id' => $ticket['group_id'] ?? null,
            'company_id' => $ticket['company_id'] ?? null,
            'product_id' => $ticket['product_id'] ?? null,
            'tags' => $ticket['tags'] ?? [],
            'custom_fields' => $ticket['custom_fields'] ?? [],
            'due_by' => $ticket['due_by'] ?? null,
            'created_at' => $ticket['created_at'] ?? null,
            'updated_at' => $ticket['updated_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    protected function shapeContact(array $contact): array
    {
        return [
            'id' => $contact['id'] ?? null,
            'name' => $contact['name'] ?? null,
            'email' => $contact['email'] ?? null,
            'phone' => $contact['phone'] ?? null,
            'mobile' => $contact['mobile'] ?? null,
            'company_id' => $contact['company_id'] ?? null,
            'job_title' => $contact['job_title'] ?? null,
            'unique_external_id' => $contact['unique_external_id'] ?? null,
            'active' => $contact['active'] ?? null,
            'tags' => $contact['tags'] ?? [],
            'custom_fields' => $contact['custom_fields'] ?? [],
            'created_at' => $contact['created_at'] ?? null,
            'updated_at' => $contact['updated_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $company
     * @return array<string, mixed>
     */
    protected function shapeCompany(array $company): array
    {
        return ['id' => $company['id'] ?? null, 'name' => $company['name'] ?? null, 'description' => $company['description'] ?? null, 'domains' => $company['domains'] ?? [], 'health_score' => $company['health_score'] ?? null, 'account_tier' => $company['account_tier'] ?? null, 'industry' => $company['industry'] ?? null, 'custom_fields' => $company['custom_fields'] ?? [], 'created_at' => $company['created_at'] ?? null, 'updated_at' => $company['updated_at'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $conversation
     * @return array<string, mixed>
     */
    protected function shapeConversation(array $conversation): array
    {
        return ['id' => $conversation['id'] ?? null, 'ticket_id' => $conversation['ticket_id'] ?? null, 'body' => $conversation['body_text'] ?? $conversation['body'] ?? null, 'incoming' => $conversation['incoming'] ?? null, 'private' => $conversation['private'] ?? null, 'user_id' => $conversation['user_id'] ?? null, 'from_email' => $conversation['from_email'] ?? null, 'to_emails' => $conversation['to_emails'] ?? [], 'attachments' => $conversation['attachments'] ?? [], 'created_at' => $conversation['created_at'] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    protected function groupField(string $title = 'Group'): array
    {
        return Field::dynamic($title, 'freshdesk.groups', null, 'Support');
    }

    /**
     * @return array<string, mixed>
     */
    protected function agentField(string $title = 'Agent'): array
    {
        return Field::dynamic($title, 'freshdesk.agents', null, 'Ada Lovelace');
    }
}
