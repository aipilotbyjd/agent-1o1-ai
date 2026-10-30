<?php

namespace App\Nodes\Integrations\Intercom;

class IntercomListLeadsWithEmailNode extends AbstractIntercomSearchTriggerNode
{
    public function type(): string
    {
        return 'intercom_list_leads_with_email';
    }

    public function name(): string
    {
        return 'Intercom: List Leads With Email';
    }

    public function description(): string
    {
        return 'Lists leads that have an email and changed lately. As a trigger it starts the flow once when a lead gives their email. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'contacts';
    }

    protected function stampField(): ?string
    {
        return 'updated_at';
    }

    protected function filters(array $config): array
    {
        return [['field' => 'role', 'operator' => '=', 'value' => 'lead'], ['field' => 'email', 'operator' => '!=', 'value' => null]];
    }

    public function triggerName(): string
    {
        return 'Intercom: Lead added email';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow once when a lead gives their email. Checked every few minutes.';
    }
}
