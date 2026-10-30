<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryCreateClientKeyNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_create_client_key';
    }

    public function name(): string
    {
        return 'Sentry: Create Client Key';
    }

    public function description(): string
    {
        return 'Creates a client key (DSN) for a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'project' => $this->projectField(),
                'name' => Field::text('Name'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->sentry($run, $config, 'POST', '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config).'/keys/', [], $this->filledOf($config, ['name' => 'name']));
    }
}
