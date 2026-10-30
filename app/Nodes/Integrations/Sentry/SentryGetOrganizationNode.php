<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SentryGetOrganizationNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_organization';
    }

    public function name(): string
    {
        return 'Sentry: Get Organization';
    }

    public function description(): string
    {
        return 'Gets an organization.';
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
                'organization' => $this->organizationField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $organization = $this->find($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/');

        return $organization === null ? ['found' => false] : ['found' => true, ...$organization];
    }
}
