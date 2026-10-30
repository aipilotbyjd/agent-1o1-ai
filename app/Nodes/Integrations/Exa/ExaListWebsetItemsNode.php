<?php

namespace App\Nodes\Integrations\Exa;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;

/**
 * The trigger registers a Websets webhook for new items (see ExaSource). It covers all the team's websets, so a delivery only makes this webset's poll look now.
 */
class ExaListWebsetItemsNode extends AbstractExaNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'exa_list_webset_items';
    }

    public function name(): string
    {
        return 'Exa: List Webset Items';
    }

    public function description(): string
    {
        return 'Lists the items a webset has found, with their enrichments. As a trigger it starts the flow for each new item.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['webset_id'],
            'properties' => [
                ...$this->credentialFields(),
                'webset_id' => $this->websetField(),
                ...$this->pageFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $page = $this->shapeList($this->exa($run, $config, 'GET', self::WEBSETS.'/websets/'.$this->pathId($config, 'webset_id', 'The webset').'/items', query: $this->pageQuery($config)));

        return ['items' => $page['items'], 'count' => $page['count'], 'next_cursor' => $page['next_cursor']];
    }

    public function triggerName(): string
    {
        return 'Exa: New webset item';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow for each new item an Exa webset finds. Instant.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'items';
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
