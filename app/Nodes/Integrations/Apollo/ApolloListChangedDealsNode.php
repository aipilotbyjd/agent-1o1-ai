<?php

namespace App\Nodes\Integrations\Apollo;

class ApolloListChangedDealsNode extends AbstractApolloRecordTriggerNode
{
    public function type(): string
    {
        return 'apollo_list_changed_deals';
    }

    public function name(): string
    {
        return 'Apollo: List Changed Deals';
    }

    public function description(): string
    {
        return 'Lists the deals changed lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'opportunities';
    }

    protected function createdOnly(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Apollo: Deal updated';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a deal is updated in Apollo. Checks every few minutes.';
    }
}
