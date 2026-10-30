<?php

namespace App\Nodes\Integrations\Zendesk;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ZendeskListGroupsNode extends AbstractZendeskNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'zendesk_list_groups';
    }

    public function name(): string
    {
        return 'Zendesk: List Groups';
    }

    public function description(): string
    {
        return 'Lists the agent groups. As a trigger it starts the flow when a group is added.';
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
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listing($run, $config, '/groups', 'groups', [], $this->shapeGroup(...));
    }

    protected function triggerItemsPath(): ?string
    {
        return 'groups';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 1000];
    }

    public function triggerName(): string
    {
        return 'Zendesk: New group';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when an agent group is added. Checked every few minutes.';
    }
}
