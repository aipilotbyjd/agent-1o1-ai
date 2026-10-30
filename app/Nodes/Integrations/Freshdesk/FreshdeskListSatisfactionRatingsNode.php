<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use Illuminate\Support\Carbon;

class FreshdeskListSatisfactionRatingsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_satisfaction_ratings';
    }

    public function name(): string
    {
        return 'Freshdesk: List Satisfaction Ratings';
    }

    public function description(): string
    {
        return 'Lists satisfaction ratings across all surveys.';
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
                'created_since' => Field::advanced(Field::datetime('Created since')),
                'user_id' => Field::advanced(Field::text('Contact id')),
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $since = filled($config['created_since'] ?? null) ? Carbon::parse((string) $config['created_since'])->utc()->format('Y-m-d\TH:i:s\Z') : null;

        return $this->listing($run, $config, '/surveys/satisfaction_ratings', 'satisfaction_ratings', ['created_since' => $since, 'user_id' => $this->optionalId($config, 'user_id', 'The contact')]);
    }
}
