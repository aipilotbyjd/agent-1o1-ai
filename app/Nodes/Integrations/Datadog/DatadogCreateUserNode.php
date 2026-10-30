<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class DatadogCreateUserNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_create_user';
    }

    public function name(): string
    {
        return 'Datadog: Create User';
    }

    public function description(): string
    {
        return 'Creates a user in the organization.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['email'],
            'properties' => [
                ...$this->credentialFields(),
                'email' => Field::text('Email', null, 'name@example.com'),
                'name' => Field::text('Name'),
                'title' => Field::advanced(Field::text('Job title')),
                'role_ids' => Field::advanced(Field::list('Role ids')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $data = ['type' => 'users', 'attributes' => array_filter(['email' => $this->requiredString($config, 'email', 'The email'), 'name' => $this->text($config, 'name'), 'title' => $this->text($config, 'title')], fn (mixed $value): bool => $value !== null)];

        if ($this->listOf($config, 'role_ids') !== []) {
            $data['relationships'] = ['roles' => ['data' => array_map(fn (string $id): array => ['id' => $id, 'type' => 'roles'], $this->listOf($config, 'role_ids'))]];
        }

        return $this->flatten((array) ($this->datadog($run, $config, 'POST', '/api/v2/users', [], ['data' => $data])['data'] ?? []));
    }
}
