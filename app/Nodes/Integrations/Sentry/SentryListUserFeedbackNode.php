<?php

namespace App\Nodes\Integrations\Sentry;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SentryListUserFeedbackNode extends AbstractSentryNode
{
    public function type(): string
    {
        return 'sentry_list_user_feedback';
    }

    public function name(): string
    {
        return 'Sentry: List User Feedback';
    }

    public function description(): string
    {
        return 'Lists the feedback users left on a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
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
                'cursor' => Field::advanced(Field::text('Page cursor', 'The "next_cursor" of the last call, for the next page.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->listOf($run, $config, '/api/0/projects/'.$this->org($run, $config).'/'.$this->project($config).'/user-feedback/', 'feedback');
    }
}
