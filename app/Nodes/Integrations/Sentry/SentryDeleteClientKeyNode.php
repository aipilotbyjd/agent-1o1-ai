<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryDeleteClientKeyNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_delete_client_key';
    }

    public function name(): string
    {
        return 'Sentry: Delete Client Key';
    }

    public function description(): string
    {
        return 'Deletes a client key; apps using its DSN stop reporting.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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
        $key = $this->pathId($config, 'key_id', 'The key id');
        $this->sentry($run, $config, 'DELETE', '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config)."/keys/{$key}/");

        return ['deleted' => true, 'id' => $key];
    }
}
