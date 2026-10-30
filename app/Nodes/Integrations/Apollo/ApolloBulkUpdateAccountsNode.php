<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ApolloBulkUpdateAccountsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_bulk_update_accounts';
    }

    public function name(): string
    {
        return 'Apollo: Bulk Update Accounts';
    }

    public function description(): string
    {
        return 'Gives several accounts the same owner, stage or name, or applies a different change to each.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'account_ids' => Field::list('Account ids', 'For the same change on all of them.'),
                'owner_id' => Field::dynamic('Owner', 'apollo.users'),
                'account_stage_id' => Field::dynamic('Account stage', 'apollo.account_stages'),
                'name' => Field::advanced(Field::text('Name')),
                'account_attributes' => Field::advanced(Field::json('Different changes', 'A list of accounts, each with its id and the fields to change. Used instead of the ids.', 'array')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->only($config, ['owner_id', 'account_stage_id', 'name', 'account_attributes']);
        $ids = $this->stringList($config, 'account_ids');

        if ($ids === [] && empty($config['account_attributes'])) {
            throw new InvalidArgumentException('Give the account ids or the different changes.');
        }

        return $this->listResult($this->apollo($run, $config, 'POST', '/accounts/bulk_update', [], [...$body, 'account_ids' => $ids ?: null]), 'accounts', $this->shapeAccount(...));
    }
}
