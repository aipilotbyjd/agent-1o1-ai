<?php

namespace App\Nodes\Integrations\Freshdesk;

class FreshdeskListNewCompaniesNode extends AbstractFreshdeskChangeTriggerNode
{
    public function type(): string
    {
        return 'freshdesk_list_new_companies';
    }

    public function name(): string
    {
        return 'Freshdesk: List New Companies';
    }

    public function description(): string
    {
        return 'Lists the companies added lately. As a trigger it starts the flow for each one. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'companies';
    }

    protected function onlyNew(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Freshdesk: New company';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a company is added in Freshdesk. Checked every few minutes.';
    }
}
