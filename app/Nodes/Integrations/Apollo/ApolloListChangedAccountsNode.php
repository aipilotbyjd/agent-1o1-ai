<?php

namespace App\Nodes\Integrations\Apollo;

class ApolloListChangedAccountsNode extends AbstractApolloRecordTriggerNode
{
    public function type(): string
    {
        return 'apollo_list_changed_accounts';
    }

    public function name(): string
    {
        return 'Apollo: List Changed Accounts';
    }

    public function description(): string
    {
        return 'Lists the accounts changed lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'accounts';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Apollo: Account updated';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a account is updated in Apollo. Checks every few minutes.';
    }
}
