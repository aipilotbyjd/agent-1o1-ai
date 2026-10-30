<?php

namespace App\Nodes\Integrations\Intercom;

class IntercomListUpdatedCompaniesNode extends AbstractIntercomSearchTriggerNode
{
    public function type(): string
    {
        return 'intercom_list_updated_companies';
    }

    public function name(): string
    {
        return 'Intercom: List Updated Companies';
    }

    public function description(): string
    {
        return 'Lists the companies changed lately. As a trigger it starts the flow for each change. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'companies';
    }

    protected function stampField(): ?string
    {
        return 'updated_at';
    }

    protected function eventStamp(array $item): ?string
    {
        return (string) ($item['updated_at'] ?? '');
    }

    public function triggerName(): string
    {
        return 'Intercom: Updated company';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a company changes in Intercom. Checked every few minutes.';
    }
}
