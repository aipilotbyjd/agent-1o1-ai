<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class SentryListEnvironmentsNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_environments';
    }

    public function name(): string
    {
        return 'Sentry: List Environments';
    }

    public function description(): string
    {
        return 'Lists the environments of an organization, or of one project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'project' => $this->projectField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $org = $this->org($run, $config);
        $path = filled($config['project'] ?? null) ? "/api/0/projects/{$org}/".$this->project($config).'/environments/' : "/api/0/organizations/{$org}/environments/";

        return $this->listOf($run, $config, $path, 'environments');
    }
}
