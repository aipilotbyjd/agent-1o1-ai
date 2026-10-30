<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryListIntegrationsNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_integrations';
    }

    public function name(): string
    {
        return 'Sentry: List Integrations';
    }

    public function description(): string
    {
        return 'Lists the third-party integrations installed on an organization.';
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
                'provider' => Field::text('Provider', 'Like slack or github.'),
                'features' => Field::advanced(Field::list('Features')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/integrations/', 'integrations', ['providerKey' => $this->text($config, 'provider'), 'features' => $this->items($config, 'features')]);
    }
}
