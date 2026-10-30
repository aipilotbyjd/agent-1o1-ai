<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloUpdateAccountStageNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_update_account_stage';
    }

    public function name(): string
    {
        return 'Apollo: Update Account Stage';
    }

    public function description(): string
    {
        return 'Moves one or more accounts to a stage.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_ids', 'account_stage_id'],
            'properties' => [
                ...$this->credentialFields(),
                'account_ids' => Field::list('Account ids'),
                'account_stage_id' => Field::dynamic('Account stage', 'apollo.account_stages'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = ['account_ids' => $this->requiredList($config, 'account_ids', 'The account ids'), 'account_stage_id' => $this->requiredString($config, 'account_stage_id', 'The stage')];

        return $this->listResult($this->apollo($run, $config, 'POST', '/accounts/bulk_update', [], $body), 'accounts', $this->shapeAccount(...));
    }
}
