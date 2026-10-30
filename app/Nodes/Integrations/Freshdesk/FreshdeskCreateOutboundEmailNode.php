<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskCreateOutboundEmailNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_outbound_email';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Outbound Email';
    }

    public function description(): string
    {
        return 'Starts a new ticket by sending an email from one of your addresses.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['subject', 'description', 'email', 'email_config_id'],
            'properties' => [
                ...$this->credentialFields(),
                'subject' => Field::text('Subject'),
                'description' => Field::textarea('Message', 'HTML is kept.'),
                'email' => Field::text('To', null, 'ada@example.com'),
                'email_config_id' => Field::dynamic('From address', 'freshdesk.email_configs'),
                'priority' => Field::advanced(Field::select('Priority', ['1' => 'Low', '2' => 'Medium', '3' => 'High', '4' => 'Urgent'], null, '1')),
                'cc_emails' => Field::advanced(Field::emails('CC')),
                'tags' => Field::advanced(Field::text('Tags', 'Separated by commas.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->shapeTicket($this->freshdesk($run, $config, 'POST', '/tickets/outbound_email', [], $this->present(['subject' => $this->requiredString($config, 'subject', 'The subject'), 'description' => $this->requiredString($config, 'description', 'The message'), 'email' => $this->email($config), 'email_config_id' => (int) $this->numericId($config, 'email_config_id', 'The from address'), 'priority' => $this->number($config, 'priority') ?? 1, 'cc_emails' => $this->words($config, 'cc_emails'), 'tags' => $this->words($config, 'tags')])));
    }
}
