<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * Tickets, contacts or companies that appeared or changed lately, as a reader and as
 * a trigger. Freshdesk lists them with `updated_since`, so a poll reads what changed
 * within the look-back, and a new-record node keeps those created inside it. Freshdesk
 * sends webhooks only from automation rules set up in its admin, so these poll; point
 * such a rule at a Webhook trigger to get them at once.
 */
abstract class AbstractFreshdeskChangeTriggerNode extends AbstractFreshdeskNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * `tickets`, `contacts` or `companies`.
     */
    abstract protected function resource(): string;

    /**
     * Whether this node is about records created (not just changed) inside the look-back.
     */
    protected function onlyNew(): bool
    {
        return false;
    }

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
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->utc();
        $resource = $this->resource();
        $query = ['updated_since' => $since->format('Y-m-d\TH:i:s\Z')];

        if ($resource === 'tickets') {
            $query += ['order_by' => $this->onlyNew() ? 'created_at' : 'updated_at', 'order_type' => 'desc'];
        }

        $rows = $this->listAll($run, $config, "/{$resource}", $query, $this->limit($config, 50));

        if ($this->onlyNew()) {
            $rows = array_values(array_filter($rows, fn (array $row): bool => filled($row['created_at'] ?? null) && Carbon::parse((string) $row['created_at'])->greaterThanOrEqualTo($since)));
        }

        $shape = match ($resource) {
            'tickets' => $this->shapeTicket(...),
            'contacts' => $this->shapeContact(...),
            default => $this->shapeCompany(...),
        };

        return [$resource => array_map($shape, $rows), 'count' => count($rows)];
    }

    protected function triggerItemsPath(): ?string
    {
        return $this->resource();
    }

    /**
     * A record that changes again is a new event; one that is created once is not.
     */
    public function triggerItemId(array $item): string
    {
        return $this->onlyNew() ? (string) ($item['id'] ?? '') : ($item['id'] ?? '').':'.($item['updated_at'] ?? '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'changed_within_minutes' => self::TRIGGER_WINDOW_MINUTES, 'limit' => 500];
    }

    public function triggerListsNewestFirst(): bool
    {
        return $this->resource() === 'tickets';
    }
}
