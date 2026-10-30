<?php

namespace App\Nodes\Integrations\Apollo;

class ApolloListNewDealsNode extends AbstractApolloRecordTriggerNode
{
    public function type(): string
    {
        return 'apollo_list_new_deals';
    }

    public function name(): string
    {
        return 'Apollo: List New Deals';
    }

    public function description(): string
    {
        return 'Lists the deals created lately, newest first. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    protected function object(): string
    {
        return 'opportunities';
    }

    protected function createdOnly(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Apollo: New deal';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a deal is created in Apollo. Checks every few minutes.';
    }
}
