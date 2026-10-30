<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared request, error handling, validation and result shaping for the Meta Ads node
 * family, on the Marketing API of the Graph API. A write is a form post whose nested
 * values are JSON text, which is what the Graph API documents. Anything that can spend
 * money is created paused unless the step says otherwise. Page calls use the Page's own
 * token, fetched with the member's.
 */
abstract class AbstractMetaAdsNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    public const string BASE_URL = 'https://graph.facebook.com/v26.0';

    public const int MAX_PAGE_SIZE = 200;

    protected const string CAMPAIGN_FIELDS = 'id,name,objective,status,effective_status,daily_budget,lifetime_budget,budget_remaining,spend_cap,bid_strategy,buying_type,special_ad_categories,start_time,stop_time,created_time,updated_time,account_id';

    protected const string AD_SET_FIELDS = 'id,name,campaign_id,status,effective_status,daily_budget,lifetime_budget,budget_remaining,bid_amount,bid_strategy,billing_event,optimization_goal,targeting,promoted_object,start_time,end_time,created_time,updated_time,account_id';

    protected const string AD_FIELDS = 'id,name,adset_id,campaign_id,status,effective_status,creative,tracking_specs,conversion_domain,issues_info,created_time,updated_time,account_id';

    protected const string CREATIVE_FIELDS = 'id,name,status,object_story_id,object_story_spec,title,body,call_to_action_type,image_hash,image_url,video_id,thumbnail_url,url_tags,account_id';

    protected const string ACCOUNT_FIELDS = 'id,account_id,name,account_status,currency,timezone_name,amount_spent,balance,spend_cap,business';

    protected const string AUDIENCE_FIELDS = 'id,name,description,subtype,customer_file_source,retention_days,approximate_count_lower_bound,approximate_count_upper_bound,delivery_status,operation_status,time_created,time_updated';

    protected const string LEAD_FIELDS = 'id,created_time,ad_id,ad_name,adset_id,adset_name,campaign_id,campaign_name,form_id,platform,is_organic,field_data';

    protected const string FORM_FIELDS = 'id,name,status,locale,leads_count,questions,created_time';

    public function category(): string
    {
        return 'meta_ads';
    }

    /**
     * Call the Graph API and return the decoded body.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params  empty values are dropped, nested ones become JSON text
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function graph(Run $run, array $config, string $method, string $path, array $params = [], ?string $token = null): array
    {
        $response = $this->graphResponse($run, $config, $method, $path, $params, $token);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     */
    protected function graphResponse(Run $run, array $config, string $method, string $path, array $params = [], ?string $token = null): Response
    {
        $method = strtoupper($method);
        $request = Http::withToken($token ?? $this->resolveAccessToken($run, $config))->acceptJson()->timeout(60);
        $url = self::BASE_URL.$path;
        $params = self::encodeParams($params);

        return $method === 'POST' ? $request->asForm()->post($url, $params) : $request->send($method, $url, ['query' => $params]);
    }

    /**
     * The token of a Page, which reading its posts and forms needs. A member whose
     * token shows none goes on with their own, and the Graph API says what is missing.
     *
     * @param  array<string, mixed>  $config
     */
    protected function pageToken(Run $run, array $config, string $pageId): string
    {
        $response = $this->graphResponse($run, $config, 'GET', "/{$pageId}", ['fields' => 'access_token']);

        $this->assertOk($response);

        return (string) ($response->json('access_token') ?: $this->resolveAccessToken($run, $config));
    }

    /**
     * Drops empty values and writes nested ones as JSON text.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function encodeParams(array $params): array
    {
        $encoded = [];

        foreach ($params as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $encoded[$name] = is_array($value) || is_object($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (is_bool($value) ? ($value ? 'true' : 'false') : $value);
        }

        return $encoded;
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        $error = $response->json('error');

        if ($response->successful() && ! is_array($error)) {
            return;
        }

        $code = (int) data_get($error, 'code', 0);
        $message = (string) (data_get($error, 'error_user_msg') ?? data_get($error, 'message') ?? "HTTP {$response->status()}");
        $hint = match (true) {
            $code === 190 || $response->status() === 401 => ' Reconnect Meta Ads: the token was refused or has expired.',
            in_array($code, [4, 17, 32, 613, 80000, 80002, 80003, 80004], true) || $response->status() === 429 => ' Meta is rate limiting the app or ad account; try again later.',
            in_array($code, [3, 10, 200, 294], true) || ($code >= 200 && $code <= 299) || $response->status() === 403 => ' The connected member or app lacks this permission. Check their role on the ad account or Page, and that the app was approved for it.',
            $code === 2635 => ' This API version is no longer served.',
            default => '',
        };

        throw new RuntimeException('Meta Ads error: '.mb_substr($message, 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * The `act_123` of an ad account setting, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function accountPath(array $config, string $key = 'account_id'): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException('The ad account is required.');
        }

        $id = str_starts_with($value, 'act_') ? substr($value, 4) : $value;

        return ctype_digit($id) ? "act_{$id}" : throw new InvalidArgumentException('The ad account must be a number, or act_ and a number.');
    }

    /**
     * A numeric object id from a setting, checked so it can sit in a URL.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function nodeId(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return ctype_digit($value) ? $value : throw new InvalidArgumentException("{$label} must be a number.");
    }

    /**
     * A Page post id, `pageid_postid`, or a plain number.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function postId(array $config, string $key = 'post_id'): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException('The post is required.');
        }

        return preg_match('/^\d+(_\d+)?$/', $value) === 1 ? $value : throw new InvalidArgumentException('The post id must be a number, or two joined by _.');
    }

    /**
     * The object insights are read for: an ad account, campaign, ad set or ad.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function insightsObject(array $config): string
    {
        foreach (['ad_id', 'ad_set_id', 'campaign_id'] as $key) {
            if (filled($config[$key] ?? null)) {
                return $this->nodeId($config, $key, 'The object');
            }
        }

        return $this->accountPath($config);
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
     * A JSON setting decoded, for the parts of the API that are too rich for form fields.
     *
     * @param  array<string, mixed>  $config
     * @return array<mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function jsonSetting(array $config, string $key, string $label, bool $required = false): array
    {
        $value = $config[$key] ?? null;
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        if (is_array($decoded) && $decoded !== []) {
            return $decoded;
        }

        return $required ? throw new InvalidArgumentException("{$label} must be JSON.") : [];
    }

    /**
     * The status a new campaign, ad set or ad starts with: paused unless told otherwise.
     *
     * @param  array<string, mixed>  $config
     */
    protected function statusOf(array $config): string
    {
        return strtoupper(trim((string) ($config['status'] ?? ''))) ?: 'PAUSED';
    }

    /**
     * What a status does to the world: anything that can deliver spends money and
     * reaches people. A templated or unknown value counts as live, so it is never
     * under-declared.
     *
     * @param  array<string, mixed>  $config
     */
    protected function statusEffect(array $config, string $key = 'status', string $default = 'PAUSED'): ActionEffect
    {
        return in_array(strtoupper(trim((string) ($config[$key] ?? $default))), ['PAUSED', 'ARCHIVED', ''], true) ? ActionEffect::Write : ActionEffect::External;
    }

    /**
     * A change of a live object: its status, or anything that raises what it can spend.
     *
     * @param  array<string, mixed>  $config
     */
    protected function changeEffect(array $config): ActionEffect
    {
        if ($this->only($config, ['daily_budget', 'lifetime_budget', 'bid_amount', 'spend_cap', 'more_fields', 'targeting', 'start_time', 'end_time', 'stop_time']) !== []) {
            return ActionEffect::External;
        }

        return filled($config['status'] ?? null) ? $this->statusEffect($config) : ActionEffect::Write;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function accountField(): array
    {
        return ['account_id' => Field::dynamic('Ad account', 'meta_ads.accounts')];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function pagingFields(int $default = 25): array
    {
        return [
            'limit' => Field::integer('Max results', null, $default, 1, self::MAX_PAGE_SIZE),
            'after' => Field::advanced(Field::text('Page cursor', 'The next_cursor of the previous read.')),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{limit: int, after: mixed}
     */
    protected function paging(array $config, int $default = 25): array
    {
        return ['limit' => max(1, min(self::MAX_PAGE_SIZE, (int) ($config['limit'] ?? $default))), 'after' => $config['after'] ?? null];
    }

    /**
     * The statuses to list, as a filter; empty lists Meta's default.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function statusFilter(array $config): array
    {
        $statuses = array_map('strtoupper', $this->stringList($config, 'effective_status'));

        return $statuses === [] ? [] : ['effective_status' => $statuses];
    }

    /**
     * The status filter's field.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function statusFilterField(): array
    {
        return ['effective_status' => Field::list('Only these statuses', 'Like ACTIVE, PAUSED, ARCHIVED or IN_PROCESS.')];
    }

    /**
     * A list with its next page's cursor.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function listResult(array $body, string $key, ?callable $shape = null): array
    {
        $items = array_values(array_filter((array) ($body['data'] ?? []), 'is_array'));

        return [$key => $shape === null ? $items : array_map($shape, $items), 'count' => count($items), 'next_cursor' => isset($body['paging']['next']) ? data_get($body, 'paging.cursors.after') : null];
    }

    /**
     * Pages through a list, up to a limit.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    protected function readAll(Run $run, array $config, string $path, array $params, int $limit, ?string $token = null): array
    {
        $items = [];
        $after = null;

        do {
            $body = $this->graph($run, $config, 'GET', $path, [...$params, 'limit' => min(self::MAX_PAGE_SIZE, $limit), 'after' => $after], $token);
            $items = [...$items, ...array_filter((array) ($body['data'] ?? []), 'is_array')];
            $after = isset($body['paging']['next']) ? data_get($body, 'paging.cursors.after') : null;
        } while ($after !== null && count($items) < $limit);

        return array_slice($items, 0, $limit);
    }

    /**
     * A lead with its answers as `field name => answer`, a list only when it holds several.
     *
     * @param  array<string, mixed>  $lead
     * @return array<string, mixed>
     */
    protected function shapeLead(array $lead): array
    {
        $fields = [];

        foreach ((array) ($lead['field_data'] ?? []) as $field) {
            if (is_array($field) && isset($field['name'])) {
                $values = array_values((array) ($field['values'] ?? []));
                $fields[$field['name']] = count($values) === 1 ? $values[0] : $values;
            }
        }

        return [...array_diff_key($lead, ['field_data' => true]), 'fields' => $fields];
    }

    /**
     * Hashes the people of a Custom Audience as Meta requires, column by column.
     * Ids that Meta takes as they are stay as they are.
     *
     * @param  list<string>  $schema
     * @param  list<array<int, mixed>>  $rows
     * @return list<list<string>>
     */
    protected function hashAudienceRows(array $schema, array $rows): array
    {
        return array_map(function (array $row) use ($schema): array {
            $cells = [];

            foreach ($schema as $index => $key) {
                $cells[] = $this->hashAudienceCell($key, trim((string) ($row[$index] ?? '')));
            }

            return $cells;
        }, $rows);
    }

    /**
     * Normalizes one value by Meta's rule for its key, then hashes it with SHA-256.
     */
    private function hashAudienceCell(string $key, string $value): string
    {
        if ($value === '' || in_array($key, ['EXTERN_ID', 'MADID', 'PAGEUID'], true)) {
            return $key === 'MADID' ? strtolower($value) : $value;
        }

        $normal = match ($key) {
            'PHONE' => ltrim((string) preg_replace('/\D/', '', $value), '0'),
            'CT' => (string) preg_replace('/[^a-z]/', '', mb_strtolower($value)),
            'ZIP' => (string) preg_replace('/\s/', '', mb_strtolower($value)),
            'FN', 'LN' => (string) preg_replace('/[\p{P}\p{S}]/u', '', mb_strtolower($value)),
            default => mb_strtolower($value),
        };

        return hash('sha256', $normal);
    }

    /**
     * The fields of a new ad, from a step's settings or one row of a bulk list.
     *
     * @param  array<string, mixed>  $ad
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function adParams(array $ad): array
    {
        $creative = $this->jsonSetting($ad, 'creative', 'The creative') ?: (filled($ad['creative_id'] ?? null) ? ['creative_id' => $this->nodeId($ad, 'creative_id', 'The creative')] : null);

        if ($creative === null) {
            throw new InvalidArgumentException('Choose a creative, or give a new one.');
        }

        return [
            'name' => $this->requiredString($ad, 'name', 'The name'),
            'adset_id' => filled($ad['adset_id'] ?? null) ? $this->nodeId($ad, 'adset_id', 'The ad set') : $this->nodeId($ad, 'ad_set_id', 'The ad set'),
            'creative' => $creative,
            'status' => $this->statusOf($ad),
            ...$this->only($ad, ['conversion_domain', 'tracking_specs']),
        ];
    }

    /**
     * The insights settings as Graph parameters.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function insightsParams(array $config): array
    {
        $fields = $this->stringList($config, 'fields') ?: ['account_name', 'campaign_id', 'campaign_name', 'adset_id', 'adset_name', 'ad_id', 'ad_name', 'spend', 'impressions', 'reach', 'clicks', 'ctr', 'cpc', 'cpm', 'actions'];
        $range = filled($config['since'] ?? null) ? ['since' => (string) $config['since'], 'until' => (string) ($config['until'] ?? now()->toDateString())] : null;

        return [
            'fields' => implode(',', $fields),
            'level' => $config['level'] ?? null,
            'time_range' => $range,
            'date_preset' => $range === null ? ($config['date_preset'] ?? null) : null,
            'time_increment' => $config['time_increment'] ?? null,
            'breakdowns' => implode(',', $this->stringList($config, 'breakdowns')),
            'action_breakdowns' => implode(',', $this->stringList($config, 'action_breakdowns')),
            'action_attribution_windows' => $this->stringList($config, 'action_attribution_windows') ?: null,
            'filtering' => $this->jsonSetting($config, 'filtering', 'The filters') ?: null,
            'sort' => isset($config['sort']) && $config['sort'] !== '' ? [(string) $config['sort']] : null,
        ];
    }

    /**
     * The one-batch session an audience upload needs. A removal is a post that says
     * `method=DELETE`, as Meta allows, since a real DELETE would put every hash in the URL.
     *
     * @return array<string, mixed>
     */
    protected function audienceSession(string $method, int $count): array
    {
        return $method === 'POST' ? ['session' => ['session_id' => random_int(1, PHP_INT_MAX), 'batch_seq' => 1, 'last_batch_flag' => true, 'estimated_num_total' => $count]] : ['method' => 'DELETE'];
    }

    /**
     * The fields shared by the conversion event nodes.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function conversionFields(): array
    {
        return [
            'pixel_id' => Field::dynamic('Pixel', 'meta_ads.pixels', null, null, ['account_id']),
            'email' => Field::text('Email', 'Sent as a SHA-256 hash, as Meta requires.'),
            'phone' => Field::text('Phone', 'With the country code. Sent as a SHA-256 hash.'),
            'event_time' => Field::datetime('Happened at', 'Within the last 7 days. Empty is now.'),
            'event_id' => Field::advanced(Field::text('Event id', 'The same id as the browser event, so it is counted once.')),
            'event_source_url' => Field::advanced(Field::text('Page address', 'Needed for website events.')),
            'client_ip_address' => Field::advanced(Field::text('Visitor IP address', 'Not hashed.')),
            'client_user_agent' => Field::advanced(Field::text('Visitor browser', 'Not hashed. Needed for website events.')),
            'fbc' => Field::advanced(Field::text('Click id (fbc)', 'Not hashed.')),
            'fbp' => Field::advanced(Field::text('Browser id (fbp)', 'Not hashed.')),
            'external_id' => Field::advanced(Field::text('Your id of the person', 'Sent as a SHA-256 hash.')),
            'test_event_code' => Field::advanced(Field::text('Test event code', 'From Events Manager, while testing. Events with it are still counted.')),
        ];
    }

    /**
     * Sends one event to a pixel or dataset.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>  $customData
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function sendConversion(Run $run, array $config, array $event, array $customData = [], array $extraUserData = []): array
    {
        $userData = array_filter([
            'em' => filled($config['email'] ?? null) ? [hash('sha256', mb_strtolower(trim((string) $config['email'])))] : null,
            'ph' => filled($config['phone'] ?? null) ? [hash('sha256', (string) preg_replace('/\D/', '', (string) $config['phone']))] : null,
            'external_id' => filled($config['external_id'] ?? null) ? [hash('sha256', trim((string) $config['external_id']))] : null,
            'client_ip_address' => $config['client_ip_address'] ?? null,
            'client_user_agent' => $config['client_user_agent'] ?? null,
            'fbc' => $config['fbc'] ?? null,
            'fbp' => $config['fbp'] ?? null,
            ...$extraUserData,
        ]);

        if ($userData === []) {
            throw new InvalidArgumentException('Give an email, a phone, a lead id or another way to match the person.');
        }

        $time = filled($config['event_time'] ?? null) ? strtotime((string) $config['event_time']) : false;
        $data = array_filter([
            'event_time' => $time === false ? now()->getTimestamp() : $time,
            'event_id' => $config['event_id'] ?? null,
            'event_source_url' => $config['event_source_url'] ?? null,
            'user_data' => $userData,
            'custom_data' => array_filter($customData, fn (mixed $value): bool => $value !== null && $value !== '') ?: null,
            ...$event,
        ]);

        $body = $this->graph($run, $config, 'POST', '/'.$this->nodeId($config, 'pixel_id', 'The pixel').'/events', ['data' => [$data], 'test_event_code' => $config['test_event_code'] ?? null]);

        return ['sent' => true, 'events_received' => $body['events_received'] ?? null, 'fbtrace_id' => $body['fbtrace_id'] ?? null];
    }
}
