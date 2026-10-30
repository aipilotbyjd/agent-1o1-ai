<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryCreateDeployNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_create_deploy';
    }

    public function name(): string
    {
        return 'Sentry: Create Deploy';
    }

    public function description(): string
    {
        return 'Records a deploy of a release to an environment.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['version', 'environment'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'version' => Field::text('Release version', null, 'backend@1.2.3'),
                'environment' => Field::text('Environment', null, 'production'),
                'name' => Field::text('Name'),
                'url' => Field::text('URL'),
                'date_started' => Field::advanced(Field::datetime('Started at')),
                'date_finished' => Field::advanced(Field::datetime('Finished at')),
                'projects' => Field::advanced(Field::list('Projects', 'Project slugs. Default all of the release.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = ['environment' => $this->requiredString($config, 'environment', 'The environment'), ...$this->filledOf($config, ['name' => 'name', 'url' => 'url', 'date_started' => 'dateStarted', 'date_finished' => 'dateFinished'])];

        if ($this->items($config, 'projects') !== []) {
            $body['projects'] = $this->items($config, 'projects');
        }

        return $this->sentry($run, $config, 'POST', '/api/0/organizations/'.$this->org($run, $config).'/releases/'.$this->version($config).'/deploys/', [], $body);
    }
}
