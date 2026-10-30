<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ApolloEnrichPersonNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_enrich_person';
    }

    public function name(): string
    {
        return 'Apollo: Enrich Person';
    }

    public function description(): string
    {
        return 'Finds a person by email, LinkedIn address, name and company, or id, and returns fresh details. Personal emails and phone numbers are revealed only when asked for. Uses Apollo credits.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email'),
                'linkedin_url' => Field::text('LinkedIn address'),
                'first_name' => Field::text('First name'),
                'last_name' => Field::text('Last name'),
                'organization_name' => Field::text('Company name'),
                'domain' => Field::text('Company domain'),
                'id' => Field::advanced(Field::text('Person id')),
                'hashed_email' => Field::advanced(Field::text('Hashed email', 'MD5 or SHA-256.')),
                'reveal_personal_emails' => Field::advanced(Field::boolean('Reveal personal emails', 'Uses more credits.')),
                'reveal_phone_number' => Field::advanced(Field::boolean('Reveal phone number', 'Uses more credits, and Apollo answers it later at the webhook address.')),
                'webhook_url' => Field::advanced(Field::text('Webhook address', 'Needed to reveal a phone number. It can point at a Webhook trigger.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = $this->only($config, ['email', 'linkedin_url', 'first_name', 'last_name', 'organization_name', 'domain', 'id', 'hashed_email', 'reveal_personal_emails', 'reveal_phone_number', 'webhook_url']);

        if ($query === []) {
            throw new InvalidArgumentException('Give at least an email, a LinkedIn address, a name or an id.');
        }

        $body = $this->apollo($run, $config, 'POST', '/people/match', $query);

        return ['found' => filled($body['person'] ?? null), 'person' => $body['person'] ?? null, 'request_id' => $body['request_id'] ?? null];
    }
}
