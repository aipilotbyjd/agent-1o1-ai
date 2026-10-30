<?php

namespace App\Nodes\Integrations\Apollo;

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
 * Shared request, error handling, validation and result shaping for the Apollo node
 * family. Apollo takes its key in the `x-api-key` header and many of its filters as
 * `name[]=value` query values, which is why the query string is built here and not by
 * the HTTP client (that would number the brackets). A record id is a hex string.
 *
 * A key acts as the workspace's longest-standing admin, and several calls (users,
 * deals, tasks, sequences) need a master key: a 403 says so.
 */
abstract class AbstractApolloNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    public const string BASE_URL = 'https://api.apollo.io/api/v1';

    public const int MAX_PAGE_SIZE = 100;

    public function category(): string
    {
        return 'apollo';
    }

    /**
     * Call Apollo and return the decoded body.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query  empty values are dropped; a list becomes `name[]=value`
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function apollo(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): array
    {
        $response = $this->apolloResponse($run, $config, $method, $path, $query, $body);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    protected function apolloResponse(Run $run, array $config, string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $queryString = self::queryString($query);
        $body = $body === null ? null : array_filter($body, fn (mixed $value): bool => $value !== null && $value !== '');
        $options = $body === null ? [] : ['json' => $body === [] ? new stdClass : $body];

        return Http::withHeaders(['x-api-key' => $this->resolveAccessToken($run, $config), 'Cache-Control' => 'no-cache'])->acceptJson()->timeout(60)
            ->send(strtoupper($method), self::BASE_URL.$path.($queryString === '' ? '' : '?'.$queryString), $options);
    }

    /**
     * `name=value&list[]=one&list[]=two`, the form Apollo documents.
     *
     * @param  array<string, mixed>  $query
     */
    public static function queryString(array $query): string
    {
        $parts = [];

        foreach ($query as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    if (is_scalar($item) && (string) $item !== '') {
                        $parts[] = rawurlencode((string) $name).'[]='.rawurlencode(is_bool($item) ? ($item ? 'true' : 'false') : (string) $item);
                    }
                }

                continue;
            }

            if ($value !== null && $value !== '') {
                $parts[] = rawurlencode((string) $name).'='.rawurlencode(is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
            }
        }

        return implode('&', $parts);
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = (string) ($response->json('error_details.message') ?? $response->json('error') ?? $response->json('message') ?? "HTTP {$response->status()}");
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect Apollo: the API key was refused.',
            $response->status() === 403 => ' This call may need a master API key, or a plan that includes it.',
            $response->status() === 422 => ' Apollo refused the values: check the fields of this step.',
            $response->status() === 429 => ' Apollo is rate limiting the key; try again later.',
            default => '',
        };

        throw new RuntimeException('Apollo error: '.mb_substr($message, 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * A record id from a setting, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function recordId(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} may only hold letters, digits, _ and -.");
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
     * A list setting: a real list, or text separated by commas or new lines.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function stringList(array $config, string $key): array
    {
        $value = $config[$key] ?? [];
        $items = is_array($value) ? $value : (preg_split('/[,\n]/', (string) $value) ?: []);

        return array_values(array_filter(array_map(fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '', $items), fn (string $item): bool => $item !== ''));
    }

    /**
     * A list setting that must not be empty.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    protected function requiredList(array $config, string $key, string $label): array
    {
        return $this->stringList($config, $key) ?: throw new InvalidArgumentException("{$label} is required.");
    }

    /**
     * A JSON setting that must hold a non-empty list of records.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    protected function records(array $config, string $key, string $label, int $max = 100): array
    {
        $value = $config[$key] ?? [];
        $records = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($records) || $records === []) {
            throw new InvalidArgumentException("{$label} must be a list of records.");
        }

        if (count($records) > $max) {
            throw new InvalidArgumentException("{$label} can hold at most {$max} records.");
        }

        return array_values($records);
    }

    /**
     * Only the given settings that were filled in.
     *
     * @param  array<string, mixed>  $config
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    protected function only(array $config, array $keys): array
    {
        return array_filter(array_intersect_key($config, array_flip($keys)), fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * The given list settings that were filled in, each kept as a list (a template gives one text).
     *
     * @param  array<string, mixed>  $config
     * @param  list<string>  $keys
     * @return array<string, list<mixed>>
     */
    protected function lists(array $config, array $keys): array
    {
        return array_map(fn (mixed $value): array => array_values((array) $value), $this->only($config, $keys));
    }

    /**
     * `page` and `per_page` from the settings.
     *
     * @param  array<string, mixed>  $config
     * @return array{page: int, per_page: int}
     */
    protected function paging(array $config, int $default = 25): array
    {
        return ['page' => max(1, (int) ($config['page'] ?? 1)), 'per_page' => max(1, min(self::MAX_PAGE_SIZE, (int) ($config['limit'] ?? $default)))];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function pagingFields(int $default = 25): array
    {
        return [
            'limit' => Field::integer('Max results', 'Per page, at most 100.', $default, 1, self::MAX_PAGE_SIZE),
            'page' => Field::advanced(Field::integer('Page', null, 1, 1)),
        ];
    }

    /**
     * The extra filters of a search, by Apollo's own parameter name.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function moreFilters(array $config): array
    {
        $filters = $config['more_filters'] ?? [];
        $filters = is_string($filters) ? json_decode($filters, true) : $filters;

        if (! is_array($filters)) {
            return [];
        }

        foreach (array_keys($filters) as $name) {
            if (preg_match('/^[a-z_]+(\[(min|max)\])?$/', (string) $name) !== 1) {
                throw new InvalidArgumentException("\"{$name}\" is not an Apollo filter name.");
            }
        }

        return $filters;
    }

    /**
     * The fields of a contact, shared by creating and updating one.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function contactFields(): array
    {
        return [
            'first_name' => Field::text('First name'),
            'last_name' => Field::text('Last name'),
            'email' => Field::text('Email'),
            'title' => Field::text('Job title'),
            'organization_name' => Field::text('Company name'),
            'account_id' => Field::advanced(Field::dynamic('Account', 'apollo.accounts')),
            'website_url' => Field::advanced(Field::text('Company website')),
            'present_raw_address' => Field::advanced(Field::text('Address')),
            'direct_phone' => Field::advanced(Field::text('Direct phone')),
            'corporate_phone' => Field::advanced(Field::text('Work phone')),
            'mobile_phone' => Field::advanced(Field::text('Mobile phone')),
            'home_phone' => Field::advanced(Field::text('Home phone')),
            'other_phone' => Field::advanced(Field::text('Other phone')),
            'contact_stage_id' => Field::advanced(Field::dynamic('Contact stage', 'apollo.contact_stages')),
            'label_names' => Field::advanced(Field::list('Lists', 'The names of the lists to add the contact to. Apollo creates a list it does not know.')),
            'typed_custom_fields' => Field::advanced(Field::keyValue('Custom fields', 'Custom field id and value.')),
        ];
    }

    /**
     * The contact's fields that were filled in, ready to send.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function contactBody(array $config): array
    {
        return $this->only($config, ['first_name', 'last_name', 'email', 'title', 'organization_name', 'account_id', 'website_url', 'present_raw_address', 'direct_phone', 'corporate_phone', 'mobile_phone', 'home_phone', 'other_phone', 'contact_stage_id', 'label_names', 'typed_custom_fields']);
    }

    /**
     * The fields of an account, shared by creating and updating one.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function accountFields(): array
    {
        return [
            'name' => Field::text('Name'),
            'domain' => Field::text('Domain', 'Without www, like apollo.io.'),
            'phone' => Field::text('Phone'),
            'raw_address' => Field::advanced(Field::text('Address')),
            'owner_id' => Field::advanced(Field::dynamic('Owner', 'apollo.users')),
            'account_stage_id' => Field::advanced(Field::dynamic('Account stage', 'apollo.account_stages')),
            'typed_custom_fields' => Field::advanced(Field::keyValue('Custom fields', 'Custom field id and value.')),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function accountBody(array $config): array
    {
        return $this->only($config, ['name', 'domain', 'phone', 'raw_address', 'owner_id', 'account_stage_id', 'typed_custom_fields']);
    }

    /**
     * The fields of a deal, shared by creating and updating one.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function dealFields(): array
    {
        return [
            'name' => Field::text('Name'),
            'amount' => Field::text('Amount', 'Digits only, in the team\'s currency.'),
            'opportunity_stage_id' => Field::dynamic('Deal stage', 'apollo.deal_stages'),
            'closed_date' => Field::text('Close date', 'YYYY-MM-DD.'),
            'owner_id' => Field::advanced(Field::dynamic('Owner', 'apollo.users')),
            'typed_custom_fields' => Field::advanced(Field::keyValue('Custom fields', 'Custom field id and value.')),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function dealBody(array $config): array
    {
        return $this->only($config, ['name', 'amount', 'opportunity_stage_id', 'closed_date', 'owner_id', 'typed_custom_fields']);
    }

    /**
     * The type choices of a task.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function taskTypeField(?string $default = 'action_item'): array
    {
        return ['type' => Field::select('Type', ['call' => 'Call', 'outreach_manual_email' => 'Email', 'linkedin_step_connect' => 'LinkedIn: connect', 'linkedin_step_message' => 'LinkedIn: message', 'linkedin_step_view_profile' => 'LinkedIn: view profile', 'linkedin_step_interact_post' => 'LinkedIn: interact with a post', 'action_item' => 'Action item'], null, $default)];
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    protected function shapeContact(array $contact): array
    {
        return [
            'id' => $contact['id'] ?? null,
            'first_name' => $contact['first_name'] ?? null,
            'last_name' => $contact['last_name'] ?? null,
            'name' => $contact['name'] ?? null,
            'email' => $contact['email'] ?? null,
            'email_status' => $contact['email_status'] ?? null,
            'title' => $contact['title'] ?? null,
            'organization_name' => $contact['organization_name'] ?? null,
            'account_id' => $contact['account_id'] ?? null,
            'owner_id' => $contact['owner_id'] ?? null,
            'contact_stage_id' => $contact['contact_stage_id'] ?? null,
            'linkedin_url' => $contact['linkedin_url'] ?? null,
            'phone_numbers' => $contact['phone_numbers'] ?? [],
            'label_ids' => $contact['label_ids'] ?? [],
            'created_at' => $contact['created_at'] ?? null,
            'updated_at' => $contact['updated_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $account
     * @return array<string, mixed>
     */
    protected function shapeAccount(array $account): array
    {
        return [
            'id' => $account['id'] ?? null,
            'name' => $account['name'] ?? null,
            'domain' => $account['domain'] ?? null,
            'phone' => $account['phone'] ?? null,
            'website_url' => $account['website_url'] ?? null,
            'linkedin_url' => $account['linkedin_url'] ?? null,
            'owner_id' => $account['owner_id'] ?? null,
            'account_stage_id' => $account['account_stage_id'] ?? null,
            'organization_id' => $account['organization_id'] ?? null,
            'label_ids' => $account['label_ids'] ?? [],
            'created_at' => $account['created_at'] ?? null,
            'updated_at' => $account['updated_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $deal
     * @return array<string, mixed>
     */
    protected function shapeDeal(array $deal): array
    {
        return [
            'id' => $deal['id'] ?? null,
            'name' => $deal['name'] ?? null,
            'amount' => $deal['amount'] ?? null,
            'account_id' => $deal['account_id'] ?? null,
            'owner_id' => $deal['owner_id'] ?? null,
            'opportunity_stage_id' => $deal['opportunity_stage_id'] ?? null,
            'closed_date' => $deal['closed_date'] ?? null,
            'is_closed' => $deal['is_closed'] ?? null,
            'is_won' => $deal['is_won'] ?? null,
            'created_at' => $deal['created_at'] ?? null,
            'updated_at' => $deal['updated_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    protected function shapeTask(array $task): array
    {
        return [
            'id' => $task['id'] ?? null,
            'type' => $task['type'] ?? null,
            'status' => $task['status'] ?? null,
            'priority' => $task['priority'] ?? null,
            'title' => $task['title'] ?? null,
            'note' => $task['note'] ?? null,
            'contact_id' => $task['contact_id'] ?? null,
            'account_id' => $task['account_id'] ?? null,
            'user_id' => $task['user_id'] ?? null,
            'due_at' => $task['due_at'] ?? null,
            'completed_at' => $task['completed_at'] ?? null,
            'created_at' => $task['created_at'] ?? null,
        ];
    }

    /**
     * The saved contact with this email, the first created when several share it.
     * Apollo's search is by keyword, so the address is compared here.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    protected function contactByEmail(Run $run, array $config, string $email): ?array
    {
        $body = ['q_keywords' => $email, 'sort_by_field' => 'contact_created_at', 'sort_ascending' => true, 'page' => 1, 'per_page' => self::MAX_PAGE_SIZE];
        $contacts = (array) ($this->apollo($run, $config, 'POST', '/contacts/search', [], $body)['contacts'] ?? []);

        foreach ($contacts as $contact) {
            if (is_array($contact) && strtolower((string) ($contact['email'] ?? '')) === $email) {
                return $contact;
            }
        }

        return null;
    }

    /**
     * The records under `$key`, shaped, with their count and total.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function listResult(array $body, string $key, ?callable $shape = null): array
    {
        $items = array_values(array_filter((array) ($body[$key] ?? []), 'is_array'));

        return [$key => $shape === null ? $items : array_map($shape, $items), 'count' => count($items), 'total' => data_get($body, 'pagination.total_entries') ?? $body['total_entries'] ?? null];
    }
}
