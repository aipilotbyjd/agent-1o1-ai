<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryGetClientKeyNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_get_client_key';
    }

    public function name(): string
    {
        return 'Sentry: Get Client Key';
    }

    public function description(): string
    {
        return 'Gets a client key (DSN) of a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project', 'key_id'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'project' => $this->projectField(),
                'key_id' => Field::text('Key id', 'From List Client Keys.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $key = $this->find($run, $config, '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config).'/keys/'.$this->pathId($config, 'key_id', 'The key id').'/');

        return $key === null ? ['found' => false] : ['found' => true, ...$key];
    }
}
