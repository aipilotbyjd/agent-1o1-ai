<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SentryCreateReleaseNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_create_release';
    }

    public function name(): string
    {
        return 'Sentry: Create Release';
    }

    public function description(): string
    {
        return 'Creates a release for one or more projects.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['version', 'projects'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'version' => Field::text('Release version', null, 'backend@1.2.3'),
                'projects' => Field::list('Projects', 'Project slugs.'),
                'ref' => Field::text('Ref', 'A commit hash or tag.'),
                'url' => Field::text('URL'),
                'date_released' => Field::datetime('Released at'),
                'commits' => Field::advanced(Field::json('Commits', 'As in the Sentry API.', 'array')),
                'refs' => Field::advanced(Field::json('Repository refs', 'Like [{"repository":"org/repo","commit":"abc"}].', 'array')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $changes = $this->filledOf($config, ['ref' => 'ref', 'url' => 'url', 'date_released' => 'dateReleased']);

        foreach (['commits' => 'Commits', 'refs' => 'Repository refs'] as $key => $label) {
            if (($value = $this->jsonOf($config, $key, $label)) !== null) {
                $changes[$key] = $value;
            }
        }

        $projects = $this->items($config, 'projects') ?: throw new InvalidArgumentException('At least one project is required.');

        return $this->shapeRelease($this->sentry($run, $config, 'POST', '/api/0/organizations/'.$this->org($run, $config).'/releases/', [], ['version' => $this->requiredString($config, 'version', 'The release version'), 'projects' => $projects, ...$changes]));
    }
}
