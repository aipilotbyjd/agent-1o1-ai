<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class SentryUpdateReleaseNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_update_release';
    }

    public function name(): string
    {
        return 'Sentry: Update Release';
    }

    public function description(): string
    {
        return 'Changes the settings filled in here on a release, such as marking it released.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['version'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'version' => Field::text('Release version', null, 'backend@1.2.3'),
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

        if ($changes === []) {
            throw new InvalidArgumentException('Fill in something to change.');
        }

        return $this->shapeRelease($this->sentry($run, $config, 'PUT', '/api/0/organizations/'.$this->org($run, $config).'/releases/'.$this->version($config).'/', [], $changes));
    }
}
