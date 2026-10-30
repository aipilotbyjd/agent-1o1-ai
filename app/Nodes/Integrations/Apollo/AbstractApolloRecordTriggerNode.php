<?php

namespace App\Nodes\Integrations\Apollo;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * Contacts, accounts or deals created or changed lately, as a reader and as a trigger.
 * Apollo's key has no webhook it can register (its resthooks belong to its partner
 * apps), so this polls. Contacts and accounts are sorted by the time asked about. Deals
 * cannot be sorted by time, so a few pages are read and the time is checked here.
 */
abstract class AbstractApolloRecordTriggerNode extends AbstractApolloNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    private const int DEAL_PAGES = 5;

    /**
     * `contacts`, `accounts` or `opportunities`.
     */
    abstract protected function object(): string;

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
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only records this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 50, 1, self::MAX_PAGE_SIZE),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440));
        $time = $this->createdOnly() ? 'created_at' : 'updated_at';
        $limit = max(1, min(self::MAX_PAGE_SIZE, (int) ($config['limit'] ?? 50)));

        $records = array_values(array_filter($this->read($run, $config, $limit), fn (array $record): bool => isset($record[$time]) && Carbon::parse((string) $record[$time])->gte($since)));

        usort($records, fn (array $a, array $b): int => strcmp((string) $b[$time], (string) $a[$time]));

        $records = array_map(match ($this->object()) {
            'contacts' => $this->shapeContact(...),
            'accounts' => $this->shapeAccount(...),
            default => $this->shapeDeal(...),
        }, array_slice($records, 0, $limit));

        return ['records' => $records, 'count' => count($records)];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array<string, mixed>>
     */
    private function read(Run $run, array $config, int $limit): array
    {
        if ($this->object() === 'opportunities') {
            $deals = [];

            for ($page = 1; $page <= self::DEAL_PAGES; $page++) {
                $found = (array) ($this->apollo($run, $config, 'GET', '/opportunities/search', ['page' => $page, 'per_page' => self::MAX_PAGE_SIZE])['opportunities'] ?? []);
                $deals = [...$deals, ...array_filter($found, 'is_array')];

                if (count($found) < self::MAX_PAGE_SIZE) {
                    break;
                }
            }

            return $deals;
        }

        $prefix = $this->object() === 'contacts' ? 'contact' : 'account';
        $body = ['sort_by_field' => $prefix.($this->createdOnly() ? '_created_at' : '_updated_at'), 'sort_ascending' => false, 'page' => 1, 'per_page' => $limit];

        return array_values(array_filter((array) ($this->apollo($run, $config, 'POST', "/{$this->object()}/search", [], $body)[$this->object()] ?? []), 'is_array'));
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
        return $this->createdOnly() ? (string) ($item['id'] ?? '') : ($item['id'] ?? '').':'.($item['updated_at'] ?? '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'changed_within_minutes' => self::TRIGGER_WINDOW_MINUTES, 'limit' => self::MAX_PAGE_SIZE];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
