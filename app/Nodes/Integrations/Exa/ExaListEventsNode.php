<?php

namespace App\Nodes\Integrations\Exa;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

/**
 * The trigger registers a Websets webhook for the chosen kind (see ExaSource). It needs one kind to subscribe to.
 */
class ExaListEventsNode extends AbstractExaNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'exa_list_events';
    }

    public function name(): string
    {
        return 'Exa: List Websets Events';
    }

    public function description(): string
    {
        return 'Lists what has happened in the team\'s websets, newest first. As a trigger it starts the flow for each event of the chosen kind.';
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
                'event_type' => Field::select('Event', ['webset.created', 'webset.deleted', 'webset.paused', 'webset.idle', 'webset.search.created', 'webset.search.updated', 'webset.search.completed', 'webset.search.canceled', 'webset.item.created', 'webset.item.enriched', 'import.created', 'import.completed', 'monitor.created', 'monitor.updated', 'monitor.deleted', 'monitor.run.created', 'monitor.run.completed', 'webset.export.created', 'webset.export.completed'], 'Leave empty for all, when listing.'),
                'created_after' => Field::advanced(Field::datetime('After')),
                ...$this->pageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $type = $this->textOf($config, 'event_type');
        $page = $this->shapeList($this->exa($run, $config, 'GET', self::WEBSETS.'/events', query: [...$this->pageQuery($config), 'createdAfter' => $this->textOf($config, 'created_after'), 'types' => $type]));

        return ['events' => $page['items'], 'count' => $page['count'], 'next_cursor' => $page['next_cursor']];
    }

    public function triggerName(): string
    {
        return 'Exa: Websets event';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when something happens in the team\'s websets: an item is found or enriched, a search or import completes, a webset goes idle. Instant.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'events';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
