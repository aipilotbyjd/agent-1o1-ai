<?php

namespace App\Nodes\Integrations\Intercom;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Contacts, conversations, companies or tickets that appeared or changed lately, as a
 * reader and as a trigger. Intercom's webhooks are set up by hand in its Developer
 * Hub and have no API, so a poll searches on the time field this node is about. To
 * get them at once, point a webhook topic at a Webhook trigger.
 */
abstract class AbstractIntercomSearchTriggerNode extends AbstractIntercomNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    private const int TRIGGER_WINDOW_MINUTES = 360;

    /**
     * `contacts`, `conversations`, `companies` or `tickets`.
     */
    abstract protected function resource(): string;

    /**
     * The time field the search filters on. Null is no time filter: the list is
     * everything matching, and each record starts the flow once.
     */
    protected function stampField(): ?string
    {
        return 'created_at';
    }

    /**
     * Filters beyond the time.
     *
     * @param  array<string, mixed>  $config
     * @return list<array{field: string, operator: string, value: mixed}>
     */
    protected function filters(array $config): array
    {
        return [];
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

    /**
     * What makes a new event, besides the record's id: a value that changes per event.
     *
     * @param  array<string, mixed>  $item
     */
    protected function eventStamp(array $item): ?string
    {
        return null;
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => $this->requiredFields(),
            'properties' => [
                ...$this->credentialFields(),
                ...$this->extraFields(),
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only records this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $resource = $this->resource();
        $filters = $this->filters($config);
        $stamp = $this->stampField();

        if ($stamp !== null) {
            $filters[] = ['field' => $stamp, 'operator' => '>', 'value' => Carbon::now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->getTimestamp()];
        }

        if ($filters === []) {
            throw new InvalidArgumentException('Nothing to search on.');
        }

        $listKey = in_array($resource, ['contacts', 'companies'], true) ? 'data' : $resource;
        $rows = $this->cursorAll($run, $config, "/{$resource}/search", $listKey, [], $this->allOf($filters), $this->limit($config, 50));
        $shape = match ($resource) {
            'contacts' => $this->shapeContact(...),
            'companies' => $this->shapeCompany(...),
            'conversations' => $this->shapeConversation(...),
            default => $this->shapeTicket(...),
        };

        return [$resource => array_map($shape, $rows), 'count' => count($rows)];
    }

    protected function triggerItemsPath(): ?string
    {
        return $this->resource();
    }

    public function triggerItemId(array $item): string
    {
        $stamp = $this->eventStamp($item);

        return ($item['id'] ?? '').($stamp !== null ? ':'.$stamp : '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'changed_within_minutes' => self::TRIGGER_WINDOW_MINUTES, 'limit' => 500];
    }
}
