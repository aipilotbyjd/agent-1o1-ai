<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryDeleteReleaseNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_delete_release';
    }

    public function name(): string
    {
        return 'Sentry: Delete Release';
    }

    public function description(): string
    {
        return 'Deletes a release and its files.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['version'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'version' => Field::text('Release version', null, 'backend@1.2.3'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $this->sentry($run, $config, 'DELETE', '/api/0/organizations/'.$this->org($run, $config).'/releases/'.$this->version($config).'/');

        return ['deleted' => true, 'version' => (string) $config['version']];
    }
}
