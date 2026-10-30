<?php

namespace App\Nodes\Integrations\Intercom;

class IntercomListNewLeadsNode extends AbstractIntercomSearchTriggerNode
{
    public function type(): string
    {
        return 'intercom_list_new_leads';
    }

    public function name(): string
    {
        return 'Intercom: List New Leads';
    }

    public function description(): string
    {
        return 'Lists the leads added lately. As a trigger it starts the flow for each one. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'contacts';
    }

    protected function filters(array $config): array
    {
        return [['field' => 'role', 'operator' => '=', 'value' => 'lead']];
    }

    public function triggerName(): string
    {
        return 'Intercom: New lead';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a lead is added in Intercom. Checked every few minutes.';
    }
}
