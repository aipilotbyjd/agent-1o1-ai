<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ApolloBulkEnrichPeopleNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_bulk_enrich_people';
    }

    public function name(): string
    {
        return 'Apollo: Bulk Enrich People';
    }

    public function description(): string
    {
        return 'Enriches up to 10 people in one call. Uses Apollo credits.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['details'],
            'properties' => [
                ...$this->credentialFields(),
                'details' => Field::json('People', 'A list of up to 10, each with any of email, linkedin_url, first_name, last_name, organization_name, domain, id.', 'array'),
                'reveal_personal_emails' => Field::advanced(Field::boolean('Reveal personal emails', 'Uses more credits.')),
                'reveal_phone_number' => Field::advanced(Field::boolean('Reveal phone numbers', 'Uses more credits, and Apollo answers it later at the webhook address.')),
                'webhook_url' => Field::advanced(Field::text('Webhook address', 'Needed to reveal phone numbers.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->apollo($run, $config, 'POST', '/people/bulk_match', $this->only($config, ['reveal_personal_emails', 'reveal_phone_number', 'webhook_url']), ['details' => $this->records($config, 'details', 'People', 10)]);

        return ['matches' => array_values((array) ($body['matches'] ?? [])), 'missing_records' => $body['missing_records'] ?? null, 'credits_consumed' => $body['credits_consumed'] ?? null, 'request_id' => $body['request_id'] ?? null];
    }
}
