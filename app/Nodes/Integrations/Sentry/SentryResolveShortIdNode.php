<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryResolveShortIdNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_resolve_short_id';
    }

    public function name(): string
    {
        return 'Sentry: Find Issue By Short Id';
    }

    public function description(): string
    {
        return 'Finds an issue by its short id, like BACKEND-1A.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['short_id'],
            'properties' => [
                ...$this->credentialFields(),
                'organization' => $this->organizationField(),
                'short_id' => Field::text('Short id', null, 'BACKEND-1A'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $found = $this->find($run, $config, '/api/0/organizations/'.$this->org($run, $config).'/shortids/'.$this->pathId($config, 'short_id', 'The short id').'/');

        return $found === null ? ['found' => false] : ['found' => true, 'organization' => $found['organizationSlug'] ?? null, 'project' => $found['projectSlug'] ?? null, ...$this->shapeIssue((array) ($found['group'] ?? []))];
    }
}
