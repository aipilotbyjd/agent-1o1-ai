<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SentryUpdateOrganizationNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_update_organization';
    }

    public function name(): string
    {
        return 'Sentry: Update Organization';
    }

    public function description(): string
    {
        return 'Changes the settings filled in here on an organization.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'name' => Field::text('Name'),
                'slug' => Field::text('Slug'),
                'default_role' => Field::advanced(Field::select('Default role', ['member' => 'Member', 'admin' => 'Admin', 'manager' => 'Manager', 'owner' => 'Owner'])),
                'settings' => Field::advanced(Field::json('More settings', 'As in the Sentry API.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = [...$this->filledOf($config, ['name' => 'name', 'slug' => 'slug', 'default_role' => 'defaultRole']), ...((array) ($this->jsonOf($config, 'settings', 'More settings') ?? []))];

        if ($body === []) {
            throw new InvalidArgumentException('Fill in something to change.');
        }

        return $this->sentry($run, $config, 'PUT', '/api/0/organizations/'.$this->org($run, $config).'/', [], $body);
    }
}
