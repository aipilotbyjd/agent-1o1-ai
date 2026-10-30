<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Contracts\DeclaresEffect;
use App\Contracts\NodeContract;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ResolvesConnectorCredential;
use App\Nodes\Support\Field;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Shared request, error handling, validation and result shaping for the LinkedIn Ads
 * node family, on the versioned Marketing API. Every call names the version, and
 * Rest.li 2.0 query values such as `(status:(values:List(ACTIVE)))` must keep their
 * brackets while the URNs inside them are encoded, so the query is written here and
 * not by the HTTP client. Anything that can spend money is created as a draft or
 * paused unless the step says otherwise.
 */
abstract class AbstractLinkedInAdsNode implements DeclaresEffect, NodeContract
{
    use ResolvesConnectorCredential;

    public const string BASE_URL = 'https://api.linkedin.com/rest';

    public const string VERSION = '202610';

    public function category(): string
    {
        return 'linkedin_ads';
    }

    /**
     * Call LinkedIn and return the decoded body (an empty array for "no content").
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $body
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function linkedin(Run $run, array $config, string $method, string $path, string $query = '', ?array $body = null, array $headers = []): array
    {
        $response = $this->linkedinResponse($run, $config, $method, $path, $query, $body, $headers);

        $this->assertOk($response);

        return (array) ($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>|null  $body
     * @param  array<string, string>  $headers
     */
    protected function linkedinResponse(Run $run, array $config, string $method, string $path, string $query = '', ?array $body = null, array $headers = []): Response
    {
        return Http::withToken($this->resolveAccessToken($run, $config))
            ->withHeaders(['Linkedin-Version' => self::VERSION, 'X-Restli-Protocol-Version' => '2.0.0', ...$headers])
            ->acceptJson()->timeout(30)
            ->send(strtoupper($method), self::BASE_URL.$path.($query === '' ? '' : '?'.$query), $body === null ? [] : ['json' => $body === [] ? new stdClass : $body]);
    }

    /**
     * Creates a record and returns its id, which LinkedIn sends in a header.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $body
     *
     * @throws RuntimeException
     */
    protected function create(Run $run, array $config, string $path, array $body, string $query = ''): string
    {
        $response = $this->linkedinResponse($run, $config, 'POST', $path, $query, $body);

        $this->assertOk($response);

        return (string) ($response->header('x-restli-id') ?: $response->header('x-linkedin-id') ?: $response->json('id') ?: '');
    }

    /**
     * Changes the given fields of a record.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $set
     *
     * @throws RuntimeException
     */
    protected function patch(Run $run, array $config, string $path, array $set, string $query = ''): void
    {
        if ($set === []) {
            throw new InvalidArgumentException('Fill in at least one field to change.');
        }

        $response = $this->linkedinResponse($run, $config, 'POST', $path, $query, ['patch' => ['$set' => $set]], ['X-RestLi-Method' => 'PARTIAL_UPDATE']);

        $this->assertOk($response);
    }

    /**
     * Deletes a record the way LinkedIn asks: a draft goes at once, anything that has
     * been live is marked for deletion.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws RuntimeException
     */
    protected function remove(Run $run, array $config, string $path, string $field = 'status'): string
    {
        $status = (string) ($this->linkedin($run, $config, 'GET', $path)[$field] ?? '');

        if ($status === 'DRAFT') {
            $this->assertOk($this->linkedinResponse($run, $config, 'DELETE', $path));

            return 'deleted';
        }

        $this->patch($run, $config, $path, [$field => 'PENDING_DELETION']);

        return 'pending_deletion';
    }

    /**
     * @throws RuntimeException
     */
    protected function assertOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = (string) ($response->json('message') ?? "HTTP {$response->status()}");
        $hint = match (true) {
            $response->status() === 401 => ' Reconnect LinkedIn Ads: the token was refused or has expired.',
            $response->status() === 403 => ' Check the member\'s role on the ad account, and that the app was approved for this LinkedIn API program.',
            $response->status() === 426 => ' This API version is no longer served.',
            $response->status() === 429 => ' LinkedIn is rate limiting the app; try again later.',
            default => '',
        };

        throw new RuntimeException('LinkedIn Ads error: '.mb_substr($message, 0, 400).(str_ends_with($message, '.') ? '' : '.').$hint);
    }

    /**
     * The numeric id at the end of an id or URN setting, checked so it can sit in a URL.
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

        $id = str_contains($value, ':') ? substr((string) strrchr($value, ':'), 1) : $value;

        return ctype_digit($id) ? $id : throw new InvalidArgumentException("{$label} must be a number or a URN ending in one.");
    }

    /**
     * The URN of an id or URN setting, like `urn:li:sponsoredCampaign:123`.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function urn(array $config, string $key, string $label, string $type): string
    {
        return "urn:li:{$type}:".$this->numericId($config, $key, $label);
    }

    /**
     * A person or organization URN from a setting.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function anyUrn(array $config, string $key, string $label): string
    {
        $value = trim((string) ($config[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }

        return preg_match('/^urn:[a-z]+:[A-Za-z]+:[A-Za-z0-9_:(),-]+$/', $value) === 1 ? $value : throw new InvalidArgumentException("{$label} must be a URN, like urn:li:organization:123.");
    }

    /**
     * A key-free id that can sit in a URL, like a lead form response id.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function plainId(array $config, string $key, string $label): string
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
     * A JSON setting decoded, for the parts of LinkedIn's API that are too rich for form fields.
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
     * `List(a,b)` with each value encoded.
     *
     * @param  list<string>  $values
     */
    public static function restliList(array $values): string
    {
        return 'List('.implode(',', array_map('rawurlencode', $values)).')';
    }

    /**
     * The `search=(status:(values:List(ACTIVE)))` value of a search; empty values are left out.
     *
     * @param  array<string, list<string>>  $criteria
     */
    public static function searchValue(array $criteria, ?bool $test = null): string
    {
        $parts = [];

        foreach ($criteria as $name => $values) {
            if ($values !== []) {
                $parts[] = "{$name}:(values:".self::restliList($values).')';
            }
        }

        if ($test !== null) {
            $parts[] = 'test:'.($test ? 'true' : 'false');
        }

        return $parts === [] ? '' : 'search=('.implode(',', $parts).')&';
    }

    /**
     * A time as milliseconds since 1970, from a date text or a number.
     *
     * @throws InvalidArgumentException
     */
    protected function epochMs(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value < 100000000000 ? (int) $value * 1000 : (int) $value;
        }

        return Carbon::parse((string) $value)->getTimestampMs();
    }

    /**
     * A `(start:(year:y,month:m,day:d),end:(...))` date range; no start means the last 30 days.
     *
     * @param  array<string, mixed>  $config
     */
    protected function dateRange(array $config): string
    {
        $start = Carbon::parse((string) ($config['date_from'] ?? now()->subDays(30)->toDateString()));
        $range = "start:(year:{$start->year},month:{$start->month},day:{$start->day})";

        if (filled($config['date_to'] ?? null)) {
            $end = Carbon::parse((string) $config['date_to']);
            $range .= ",end:(year:{$end->year},month:{$end->month},day:{$end->day})";
        }

        return "({$range})";
    }

    /**
     * Who pays attention to a status: anything that can serve is a real-world effect.
     * A templated or unknown value counts as live, so it is never under-declared.
     *
     * @param  array<string, mixed>  $config
     */
    protected function statusEffect(array $config, string $key = 'status', string $default = 'PAUSED'): ActionEffect
    {
        return in_array(strtoupper(trim((string) ($config[$key] ?? $default))), ['DRAFT', 'PAUSED', 'ARCHIVED', 'CANCELED', 'CANCELLED', ''], true) ? ActionEffect::Write : ActionEffect::External;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function accountField(): array
    {
        return ['account_id' => Field::dynamic('Ad account', 'linkedin_ads.accounts')];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function pageSizeField(int $default = 25, int $max = 1000): array
    {
        return [
            'limit' => Field::integer('Max results', null, $default, 1, $max),
            'page_token' => Field::advanced(Field::text('Page token', 'The next_page_token of the previous read.')),
        ];
    }

    /**
     * `pageSize` and, when given, `pageToken`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function pageQuery(array $config, int $default = 25, int $max = 1000): string
    {
        $query = 'pageSize='.max(1, min($max, (int) ($config['limit'] ?? $default)));

        return filled($config['page_token'] ?? null) ? $query.'&pageToken='.rawurlencode((string) $config['page_token']) : $query;
    }

    /**
     * Shapes a list with its paging token.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function elements(array $body, string $key, ?callable $shape = null): array
    {
        $items = array_values(array_filter((array) ($body['elements'] ?? []), 'is_array'));

        return [$key => $shape === null ? $items : array_map($shape, $items), 'count' => count($items), 'next_page_token' => data_get($body, 'metadata.nextPageToken')];
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    protected function shapeCampaignGroup(array $group): array
    {
        return ['id' => isset($group['id']) ? (string) $group['id'] : null, 'name' => $group['name'] ?? null, 'status' => $group['status'] ?? null, 'account' => $group['account'] ?? null, 'total_budget' => $group['totalBudget'] ?? null, 'run_schedule' => $group['runSchedule'] ?? null, 'test' => $group['test'] ?? null, 'serving_statuses' => $group['servingStatuses'] ?? [], 'updated_at_ms' => data_get($group, 'changeAuditStamps.lastModified.time'), 'created_at_ms' => data_get($group, 'changeAuditStamps.created.time')];
    }

    /**
     * @param  array<string, mixed>  $campaign
     * @return array<string, mixed>
     */
    protected function shapeCampaign(array $campaign): array
    {
        return ['id' => isset($campaign['id']) ? (string) $campaign['id'] : null, 'name' => $campaign['name'] ?? null, 'status' => $campaign['status'] ?? null, 'type' => $campaign['type'] ?? null, 'objective_type' => $campaign['objectiveType'] ?? null, 'account' => $campaign['account'] ?? null, 'campaign_group' => $campaign['campaignGroup'] ?? null, 'cost_type' => $campaign['costType'] ?? null, 'daily_budget' => $campaign['dailyBudget'] ?? null, 'total_budget' => $campaign['totalBudget'] ?? null, 'unit_cost' => $campaign['unitCost'] ?? null, 'run_schedule' => $campaign['runSchedule'] ?? null, 'targeting_criteria' => $campaign['targetingCriteria'] ?? null, 'serving_statuses' => $campaign['servingStatuses'] ?? [], 'updated_at_ms' => data_get($campaign, 'changeAuditStamps.lastModified.time'), 'created_at_ms' => data_get($campaign, 'changeAuditStamps.created.time')];
    }

    /**
     * @param  array<string, mixed>  $creative
     * @return array<string, mixed>
     */
    protected function shapeCreative(array $creative): array
    {
        return ['id' => isset($creative['id']) ? (string) $creative['id'] : null, 'name' => $creative['name'] ?? null, 'intended_status' => $creative['intendedStatus'] ?? null, 'is_serving' => $creative['isServing'] ?? null, 'campaign' => $creative['campaign'] ?? null, 'account' => $creative['account'] ?? null, 'content' => $creative['content'] ?? null, 'review' => $creative['review'] ?? null, 'serving_hold_reasons' => $creative['servingHoldReasons'] ?? [], 'updated_at_ms' => $creative['lastModifiedAt'] ?? null, 'created_at_ms' => $creative['createdAt'] ?? null];
    }

    /**
     * A lead form response with its answers in one list.
     *
     * @param  array<string, mixed>  $lead
     * @return array<string, mixed>
     */
    protected function shapeLead(array $lead): array
    {
        $answers = [];

        foreach ((array) data_get($lead, 'formResponse.answers', []) as $answer) {
            if (is_array($answer)) {
                $answers[] = ['question_id' => $answer['questionId'] ?? null, 'text' => data_get($answer, 'answerDetails.textQuestionAnswer.answer'), 'options' => data_get($answer, 'answerDetails.multipleChoiceAnswer.options')];
            }
        }

        return ['id' => $lead['id'] ?? null, 'lead_type' => $lead['leadType'] ?? null, 'form' => $lead['versionedLeadGenFormUrn'] ?? null, 'submitted_at_ms' => $lead['submittedAt'] ?? null, 'test_lead' => $lead['testLead'] ?? null, 'owner' => $lead['owner'] ?? null, 'submitter' => $lead['submitter'] ?? null, 'campaign' => data_get($lead, 'leadMetadata.sponsoredLeadMetadata.campaign'), 'creative' => data_get($lead, 'associatedEntity.associatedCreative'), 'answers' => $answers, 'consents' => data_get($lead, 'formResponse.consentResponses', [])];
    }

    /**
     * The `(sponsoredAccount:urn...)` or `(organization:urn...)` owner of a form, from the settings.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function formOwner(array $config): string
    {
        if (filled($config['organization_id'] ?? null)) {
            return '(organization:'.rawurlencode($this->urn($config, 'organization_id', 'The organization', 'organization')).')';
        }

        return '(sponsoredAccount:'.rawurlencode($this->urn($config, 'account_id', 'The ad account', 'sponsoredAccount')).')';
    }

    /**
     * The form owner as an object, for a request body.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     *
     * @throws InvalidArgumentException
     */
    protected function formOwnerBody(array $config): array
    {
        return filled($config['organization_id'] ?? null) ? ['organization' => $this->urn($config, 'organization_id', 'The organization', 'organization')] : ['sponsoredAccount' => $this->urn($config, 'account_id', 'The ad account', 'sponsoredAccount')];
    }

    /**
     * The fields that pick the owner of a form.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function formOwnerFields(): array
    {
        return [
            'account_id' => Field::dynamic('Ad account', 'linkedin_ads.accounts', 'The owner of the forms, for ad leads.'),
            'organization_id' => Field::advanced(Field::text('Or a company page id', 'The owner of the forms, for page and event leads. Used instead of the ad account.')),
        ];
    }

    /**
     * Lead form responses of an owner.
     *
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     *
     * @throws RuntimeException
     */
    protected function readLeads(Run $run, array $config, string $leadType, ?int $sinceMs = null): array
    {
        $query = 'q=owner&owner='.$this->formOwner($config)."&leadType=(leadType:{$leadType})&count=".max(1, min(100, (int) ($config['limit'] ?? 25)));

        if (filled($config['form_id'] ?? null)) {
            $query .= '&versionedLeadGenFormUrn='.rawurlencode('urn:li:versionedLeadGenForm:(urn:li:leadGenForm:'.$this->numericId($config, 'form_id', 'The form').','.max(1, (int) ($config['form_version'] ?? 1)).')');
        }

        if ($sinceMs !== null) {
            $query .= "&submittedAtTimeRange=(start:{$sinceMs},end:".now()->getTimestampMs().')';
        }

        if ((bool) ($config['test_leads_only'] ?? false)) {
            $query .= '&limitedToTestLeads=true';
        }

        return array_values(array_filter((array) ($this->linkedin($run, $config, 'GET', '/leadFormResponses', $query)['elements'] ?? []), 'is_array'));
    }

    /**
     * The budgets, bid and other options a campaign can be given or changed by.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function campaignExtras(array $config): array
    {
        $extras = [];

        foreach (['daily_budget' => 'dailyBudget', 'total_budget' => 'totalBudget', 'unit_cost' => 'unitCost'] as $setting => $field) {
            if (filled($config[$setting] ?? null)) {
                $extras[$field] = ['amount' => (string) $config[$setting], 'currencyCode' => strtoupper($this->requiredString($config, 'currency', 'The currency'))];
            }
        }

        foreach (['objective_type' => 'objectiveType', 'associated_entity' => 'associatedEntity', 'optimization_target_type' => 'optimizationTargetType', 'political_intent' => 'politicalIntent', 'offsite_delivery_enabled' => 'offsiteDeliveryEnabled', 'audience_expansion_enabled' => 'audienceExpansionEnabled'] as $setting => $field) {
            if (isset($config[$setting]) && $config[$setting] !== '') {
                $extras[$field] = $config[$setting];
            }
        }

        return $extras;
    }

    /**
     * A person for an audience: the email hashed, as LinkedIn requires, and the rest as given.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function audienceUser(array $config, string $action): array
    {
        $userIds = [];

        if (filled($config['email'] ?? null)) {
            $userIds[] = ['idType' => 'SHA256_EMAIL', 'idValue' => hash('sha256', strtolower(trim((string) $config['email'])))];
        }

        if (filled($config['google_aid'] ?? null)) {
            $userIds[] = ['idType' => 'GOOGLE_AID', 'idValue' => (string) $config['google_aid']];
        }

        if ($userIds === [] && (blank($config['first_name'] ?? null) || blank($config['last_name'] ?? null))) {
            throw new InvalidArgumentException('Give an email, a mobile ad id, or a first and last name.');
        }

        return array_filter(['action' => $action, 'userIds' => $userIds, 'firstName' => $config['first_name'] ?? null, 'lastName' => $config['last_name'] ?? null, 'title' => $config['title'] ?? null, 'company' => $config['company'] ?? null, 'country' => $config['country'] ?? null]);
    }

    /**
     * A company for an audience.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    protected function audienceCompany(array $config, string $action): array
    {
        $company = array_filter(['companyName' => $config['company_name'] ?? null, 'organizationUrn' => $config['organization_urn'] ?? null, 'companyWebsiteDomain' => $config['company_website_domain'] ?? null, 'companyEmailDomain' => $config['company_email_domain'] ?? null, 'companyPageUrl' => $config['company_page_url'] ?? null]);

        if ($company === []) {
            throw new InvalidArgumentException('Give a company name, URN, website domain, email domain or page address.');
        }

        return array_filter(['action' => $action, ...$company, 'stockSymbol' => $config['stock_symbol'] ?? null, 'industries' => $this->stringList($config, 'industries') ?: null, 'city' => $config['city'] ?? null, 'state' => $config['state'] ?? null, 'country' => $config['country'] ?? null, 'postalCode' => $config['postal_code'] ?? null]);
    }

    /**
     * The facets, dates, granularity and metrics of an analytics read, after its first parameter.
     *
     * @param  array<string, mixed>  $config
     *
     * @throws InvalidArgumentException
     */
    protected function analyticsQuery(array $config): string
    {
        $facets = [
            'accounts' => ['account_ids', 'sponsoredAccount'],
            'campaignGroups' => ['campaign_group_ids', 'sponsoredCampaignGroup'],
            'campaigns' => ['campaign_ids', 'sponsoredCampaign'],
            'creatives' => ['creative_ids', 'sponsoredCreative'],
        ];
        $query = '';

        foreach ($facets as $facet => [$setting, $type]) {
            $ids = $this->stringList($config, $setting);

            if ($ids !== []) {
                $query .= "&{$facet}=".self::restliList(array_map(fn (string $id): string => $this->urn(['id' => $id], 'id', 'The id', $type), $ids));
            }
        }

        if ($query === '') {
            throw new InvalidArgumentException('Give at least one ad account, campaign group, campaign or creative.');
        }

        $fields = $this->stringList($config, 'fields') ?: ['impressions', 'clicks', 'costInLocalCurrency', 'landingPageClicks', 'externalWebsiteConversions', 'oneClickLeads', 'likes', 'comments', 'shares', 'follows'];

        foreach ($fields as $field) {
            if (preg_match('/^[A-Za-z0-9]+$/', $field) !== 1) {
                throw new InvalidArgumentException("\"{$field}\" is not a LinkedIn metric name.");
            }
        }

        return '&dateRange='.$this->dateRange($config).'&timeGranularity='.rawurlencode((string) ($config['time_granularity'] ?? 'ALL')).$query.'&fields='.implode(',', array_unique([...$fields, 'dateRange', 'pivotValues']));
    }

    /**
     * The result of a read: the `/rest` paging or the plain `paging` block.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function indexedElements(array $body, string $key, ?callable $shape = null): array
    {
        $items = array_values(array_filter((array) ($body['elements'] ?? []), 'is_array'));

        return [$key => $shape === null ? $items : array_map($shape, $items), 'count' => count($items), 'total' => data_get($body, 'paging.total')];
    }
}
