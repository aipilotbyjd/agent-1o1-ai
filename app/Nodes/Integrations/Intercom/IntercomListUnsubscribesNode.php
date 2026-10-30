<?php

namespace App\Nodes\Integrations\Intercom;

class IntercomListUnsubscribesNode extends AbstractIntercomSearchTriggerNode
{
    public function type(): string
    {
        return 'intercom_list_unsubscribes';
    }

    public function name(): string
    {
        return 'Intercom: List Unsubscribes';
    }

    public function description(): string
    {
        return 'Lists contacts unsubscribed from email who changed lately. As a trigger it starts the flow once for each one. Checked every few minutes.';
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
        return [['field' => 'unsubscribed_from_emails', 'operator' => '=', 'value' => true]];
    }

    public function triggerName(): string
    {
        return 'Intercom: New unsubscription';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow once when a contact unsubscribes from email. Checked every few minutes.';
    }
}
