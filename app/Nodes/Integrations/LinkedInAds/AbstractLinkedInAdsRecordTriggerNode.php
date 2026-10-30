<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * Campaign groups, campaigns or creatives of an ad account created or changed lately,
 * as a reader and as a trigger. LinkedIn only pushes lead events, so this polls: the
 * newest ids first, with the time compared here because the search cannot filter on it.
 */
abstract class AbstractLinkedInAdsRecordTriggerNode extends AbstractLinkedInAdsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * `adCampaignGroups`, `adCampaigns` or `creatives`.
     */
    abstract protected function resource(): string;

    /**
     * Whether it is about records created (true) or changed (false).
     */
    abstract protected function createdOnly(): bool;

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only records this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 50, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $account = $this->numericId($config, 'account_id', 'The ad account');
        $limit = max(1, min(100, (int) ($config['limit'] ?? 50)));
        $query = $this->resource() === 'creatives' ? 'q=criteria&sortOrder=DESCENDING' : 'q=search&sortOrder=DESCENDING';
        $body = $this->linkedin($run, $config, 'GET', "/adAccounts/{$account}/{$this->resource()}", $query."&pageSize={$limit}");

        $shaped = array_map(match ($this->resource()) {
            'adCampaignGroups' => $this->shapeCampaignGroup(...),
            'adCampaigns' => $this->shapeCampaign(...),
            default => $this->shapeCreative(...),
        }, array_values(array_filter((array) ($body['elements'] ?? []), 'is_array')));

        $since = Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->getTimestampMs();
        $time = $this->createdOnly() ? 'created_at_ms' : 'updated_at_ms';
        $records = array_values(array_filter($shaped, fn (array $record): bool => (int) ($record[$time] ?? 0) >= $since));

        return ['records' => $records, 'count' => count($records)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'records';
    }

    /**
     * A record that is changed again is a new event; one that is created once is not.
     */
    public function triggerItemId(array $item): string
    {
        return $this->createdOnly() ? (string) ($item['id'] ?? '') : ($item['id'] ?? '').':'.($item['updated_at_ms'] ?? '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'changed_within_minutes' => self::TRIGGER_WINDOW_MINUTES, 'limit' => 100];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
