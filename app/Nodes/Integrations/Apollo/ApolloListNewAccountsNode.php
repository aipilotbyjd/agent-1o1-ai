<?php

namespace App\Nodes\Integrations\Apollo;

class ApolloListNewAccountsNode extends AbstractApolloRecordTriggerNode
{
    public function type(): string
    {
        return 'apollo_list_new_accounts';
    }

    public function name(): string
    {
        return 'Apollo: List New Accounts';
    }

    public function description(): string
    {
        return 'Lists the accounts created lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'accounts';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Apollo: New account';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a account is created in Apollo. Checks every few minutes.';
    }
}
