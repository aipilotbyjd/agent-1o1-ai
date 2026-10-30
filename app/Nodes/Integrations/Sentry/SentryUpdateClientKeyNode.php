<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SentryUpdateClientKeyNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_update_client_key';
    }

    public function name(): string
    {
        return 'Sentry: Update Client Key';
    }

    public function description(): string
    {
        return 'Renames a client key, or switches it on or off.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
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
                'name' => Field::text('Name'),
                'active' => Field::select('Status', ['on' => 'Active', 'off' => 'Inactive']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = $this->filledOf($config, ['name' => 'name']);

        if (filled($config['active'] ?? null)) {
            $body['isActive'] = $config['active'] === 'on';
        }

        if ($body === []) {
            throw new InvalidArgumentException('Fill in something to change.');
        }

        return $this->sentry($run, $config, 'PUT', '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config).'/keys/'.$this->pathId($config, 'key_id', 'The key id').'/', [], $body);
    }
}
