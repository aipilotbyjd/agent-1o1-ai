<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

/**
 * Tickets, people or organizations that appeared or changed lately, as a reader and
 * as a trigger. Zendesk's search filters by creation or change time and sorts newest
 * first, so a poll asks for what is inside the look-back. A webhook (`ZendeskSource`)
 * makes the poll look at once; the poll's own dedupe decides what runs.
 */
abstract class AbstractZendeskSearchTriggerNode extends AbstractZendeskNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * `ticket`, `user` or `organization`.
     */
    abstract protected function resource(): string;

    /**
     * What else the search asks for, such as `status:solved`.
     *
     * @param  array<string, mixed>  $config
     */
    protected function searchFilter(array $config): string
    {
        return '';
    }

    /**
     * The search's time field: `created` or `updated`.
     */
    protected function stamp(): string
    {
        return 'created';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function extraFields(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    protected function requiredFields(): array
    {
        return [];
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => $this->requiredFields(),
            'properties' => [
                ...$this->credentialFields(),
                ...$this->extraFields(),
                'search_filter' => Field::advanced(Field::text('Also match', 'More search terms, e.g. group:Support priority:high.')),
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only records this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->utc()->format('Y-m-d\TH:i:s\Z');
        $query = trim("type:{$this->resource()} {$this->stamp()}>{$since} {$this->searchFilter($config)} ".((string) ($config['search_filter'] ?? '')));

        $rows = $this->listAll($run, $config, '/search', 'results', [
            'query' => $query,
            'sort_by' => $this->stamp() === 'created' ? 'created_at' : 'updated_at',
            'sort_order' => 'desc',
        ], $this->limit($config, 50));

        $key = $this->triggerItemsPath() ?? 'results';
        $shape = match ($this->resource()) {
            'ticket' => $this->shapeTicket(...),
            'user' => $this->shapeUser(...),
            default => $this->shapeOrganization(...),
        };

        return [$key => array_map($shape, $rows), 'count' => count($rows)];
    }

    /**
     * A record that changes again is a new event; one that is created once is not.
     */
    public function triggerItemId(array $item): string
    {
        return $this->stamp() === 'updated' ? ($item['id'] ?? '').':'.($item['updated_at'] ?? '') : (string) ($item['id'] ?? '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'changed_within_minutes' => self::TRIGGER_WINDOW_MINUTES, 'limit' => 1000];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
