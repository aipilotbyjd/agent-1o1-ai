<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryListClientKeysNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_client_keys';
    }

    public function name(): string
    {
        return 'Sentry: List Client Keys';
    }

    public function description(): string
    {
        return 'Lists the client keys (DSNs) of a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
                'status' => Field::advanced(Field::select('Status', ['active' => 'Active', 'inactive' => 'Inactive'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config).'/keys/', 'keys', ['status' => $this->text($config, 'status')], fn (array $key): array => ['id' => $key['id'] ?? null, 'name' => $key['name'] ?? null, 'dsn' => data_get($key, 'dsn.public'), 'is_active' => $key['isActive'] ?? null, 'created_at' => $key['dateCreated'] ?? null, 'rate_limit' => $key['rateLimit'] ?? null]);
    }
}
