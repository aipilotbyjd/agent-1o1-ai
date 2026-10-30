<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryGetReleaseNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_release';
    }

    public function name(): string
    {
        return 'Sentry: Get Release';
    }

    public function description(): string
    {
        return 'Gets a release.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
        $release = $this->find($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/releases/'.$this->version($config).'/');

        return $release === null ? ['found' => false] : ['found' => true, ...$this->shapeRelease($release)];
    }
}
