<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class FreshdeskListTicketsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_tickets';
    }

    public function name(): string
    {
        return 'Freshdesk: List Tickets';
    }

    public function description(): string
    {
        return 'Lists tickets, newest first. By default Freshdesk only shows the last 30 days; use Updated since for older ones.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'filter' => Field::advanced(Field::select('Filter', ['new_and_my_open' => 'New and my open', 'watching' => 'Watching', 'spam' => 'Spam', 'deleted' => 'Deleted'])),
                'requester_id' => Field::advanced(Field::text('Requester id')),
                'email' => Field::advanced(Field::text('Requester email')),
                'company_id' => Field::advanced(Field::dynamic('Company', 'freshdesk.companies')),
                'updated_since' => Field::advanced(Field::datetime('Updated since')),
                'include' => Field::advanced(Field::text('Also include', 'Any of requester, stats, description, separated by commas.')),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = filled($config['updated_since'] ?? null) ? Carbon::parse((string) $config['updated_since'])->utc()->format('Y-m-d\TH:i:s\Z') : null;

        return $this->listing($run, $config, '/tickets', 'tickets', ['filter' => $this->text($config, 'filter'), 'requester_id' => $this->optionalId($config, 'requester_id', 'The requester'), 'email' => $this->text($config, 'email'), 'company_id' => $this->optionalId($config, 'company_id', 'The company'), 'updated_since' => $since, 'include' => $this->text($config, 'include'), 'order_type' => 'desc'], $this->shapeTicket(...));
    }
}
