<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SentryUpdateProjectNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_update_project';
    }

    public function name(): string
    {
        return 'Sentry: Update Project';
    }

    public function description(): string
    {
        return 'Changes the settings filled in here on a project.';
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
                'platform' => Field::text('Platform'),
                'slug' => Field::advanced(Field::text('Slug')),
                'settings' => Field::advanced(Field::json('More settings', 'As in the Sentry API.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = [...$this->filledOf($config, ['name' => 'name', 'slug' => 'slug', 'platform' => 'platform']), ...((array) ($this->jsonOf($config, 'settings', 'More settings') ?? []))];

        if ($body === []) {
            throw new InvalidArgumentException('Fill in something to change.');
        }

        return $this->shapeProject($this->sentry($run, $config, 'PUT', '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config).'/', [], $body));
    }
}
