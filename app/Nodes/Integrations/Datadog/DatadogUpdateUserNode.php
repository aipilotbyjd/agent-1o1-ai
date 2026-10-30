<?php

namespace App\Nodes\Integrations\Datadog;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class DatadogUpdateUserNode extends AbstractDatadogNode
{
    public function type(): string
    {
        return 'datadog_update_user';
    }

    public function name(): string
    {
        return 'Datadog: Update User';
    }

    public function description(): string
    {
        return 'Changes a user\'s name, email, title or disabled status.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['user_id'],
            'properties' => [
                ...$this->credentialFields(),
                'user_id' => $this->userField(),
                'name' => Field::text('Name'),
                'email' => Field::text('Email'),
                'title' => Field::text('Job title'),
                'disabled' => Field::advanced(Field::boolean('Disabled')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'user_id', 'The user');
        $attributes = array_filter(['name' => $this->text($config, 'name'), 'email' => $this->text($config, 'email'), 'title' => $this->text($config, 'title'), 'disabled' => isset($config['disabled']) && $config['disabled'] !== '' ? (bool) $config['disabled'] : null], fn (mixed $value): bool => $value !== null);

        if ($attributes === []) {
            throw new InvalidArgumentException('Fill in something to change.');
        }

        return $this->flatten((array) ($this->datadog($run, $config, 'PATCH', "/api/v2/users/{$id}", [], ['data' => ['type' => 'users', 'id' => rawurldecode($id), 'attributes' => $attributes]])['data'] ?? []));
    }
}
