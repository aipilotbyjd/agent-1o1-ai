<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryListReleaseFilesNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_release_files';
    }

    public function name(): string
    {
        return 'Sentry: List Release Files';
    }

    public function description(): string
    {
        return 'Lists the files uploaded to a release, such as source maps.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
                'cursor' => Field::advanced(Field::text('Page cursor', 'The "next_cursor" of the last call, for the next page.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/releases/'.$this->version($config).'/files/', 'files');
    }
}
