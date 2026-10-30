<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomListContactSubscriptionsNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_list_contact_subscriptions';
    }

    public function name(): string
    {
        return 'Intercom: List Contact\'s Subscriptions';
    }

    public function description(): string
    {
        return 'Lists the subscription types a contact has opted in or out of.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['contact_id'],
            'properties' => [
                ...$this->credentialFields(),
                'contact_id' => Field::text('Contact id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'contact_id', 'The contact');
        $rows = (array) ($this->intercom($run, $config, 'GET', "/contacts/{$id}/subscriptions")['data'] ?? []);

        return ['subscriptions' => $rows, 'count' => count($rows)];
    }
}
