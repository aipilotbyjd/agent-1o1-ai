<?php

namespace App\Nodes\Integrations\Apollo;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ApolloBulkEnrichOrganizationsNode extends AbstractApolloNode
{
    public function type(): string
    {
        return 'apollo_bulk_enrich_organizations';
    }

    public function name(): string
    {
        return 'Apollo: Bulk Enrich Organizations';
    }

    public function description(): string
    {
        return 'Enriches up to 10 companies by domain in one call. Uses Apollo credits.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['domains'],
            'properties' => [
                ...$this->credentialFields(),
                'domains' => Field::list('Domains', 'Up to 10, like apollo.io.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $domains = $this->requiredList($config, 'domains', 'The domains');

        if (count($domains) > 10) {
            throw new InvalidArgumentException('Apollo takes at most 10 domains at a time.');
        }

        $body = $this->apollo($run, $config, 'POST', '/organizations/bulk_enrich', ['domains' => $domains]);

        return ['organizations' => array_values((array) ($body['organizations'] ?? [])), 'missing_records' => $body['missing_records'] ?? null];
    }
}
