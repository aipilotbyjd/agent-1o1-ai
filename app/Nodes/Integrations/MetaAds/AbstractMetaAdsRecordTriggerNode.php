<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * Campaigns, ad sets or ads of an ad account created or changed lately, as a reader
 * and as a trigger. Meta has no webhook for these, so this polls and compares the time
 * here. Archived and deleted objects are not listed by Meta unless asked for.
 */
abstract class AbstractMetaAdsRecordTriggerNode extends AbstractMetaAdsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * `campaigns`, `adsets` or `ads`.
     */
    abstract protected function edge(): string;

    /**
     * The fields to read.
     */
    abstract protected function fields(): string;

    /**
     * Whether it is about objects created (true) or changed (false).
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
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only objects this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 50, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $time = $this->createdOnly() ? 'created_time' : 'updated_time';
        $since = Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440));
        $limit = max(1, min(500, (int) ($config['limit'] ?? 50)));
        $found = $this->readAll($run, $config, '/'.$this->accountPath($config).'/'.$this->edge(), ['fields' => $this->fields()], $limit);

        $records = array_values(array_filter($found, fn (array $record): bool => isset($record[$time]) && Carbon::parse((string) $record[$time])->gte($since)));

        usort($records, fn (array $a, array $b): int => Carbon::parse((string) $b[$time])->getTimestamp() <=> Carbon::parse((string) $a[$time])->getTimestamp());

        return ['records' => $records, 'count' => count($records)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'records';
    }

    /**
     * An object that is changed again is a new event; one that is created once is not.
     */
    public function triggerItemId(array $item): string
    {
        return $this->createdOnly() ? (string) ($item['id'] ?? '') : ($item['id'] ?? '').':'.($item['updated_time'] ?? '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'changed_within_minutes' => self::TRIGGER_WINDOW_MINUTES, 'limit' => 500];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
