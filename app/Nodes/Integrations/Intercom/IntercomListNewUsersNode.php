<?php

namespace App\Nodes\Integrations\Intercom;

class IntercomListNewUsersNode extends AbstractIntercomSearchTriggerNode
{
    public function type(): string
    {
        return 'intercom_list_new_users';
    }

    public function name(): string
    {
        return 'Intercom: List New Users';
    }

    public function description(): string
    {
        return 'Lists the users added lately. As a trigger it starts the flow for each one. Checked every few minutes.';
    }

    protected function resource(): string
    {
        return 'contacts';
    }

    protected function filters(array $config): array
    {
        return [['field' => 'role', 'operator' => '=', 'value' => 'user']];
    }

    public function triggerName(): string
    {
        return 'Intercom: New user';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a user is added in Intercom. Checked every few minutes.';
    }
}
