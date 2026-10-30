<?php

namespace App\Nodes\Integrations\Intercom;

class IntercomListNewCompaniesNode extends AbstractIntercomSearchTriggerNode
{
    public function type(): string
    {
        return 'intercom_list_new_companies';
    }

    public function name(): string
    {
        return 'Intercom: List New Companies';
    }

    public function description(): string
    {
        return 'Lists the companies added lately. As a trigger it starts the flow for each one. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'companies';
    }

    public function triggerName(): string
    {
        return 'Intercom: New company';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a company is added in Intercom. Checked every few minutes.';
    }
}
