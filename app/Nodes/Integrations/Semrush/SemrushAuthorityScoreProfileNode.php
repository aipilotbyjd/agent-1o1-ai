<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushAuthorityScoreProfileNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_authority_score_profile';
    }

    public function name(): string
    {
        return 'Semrush: Authority Score Profile';
    }

    public function description(): string
    {
        return 'The number of referring domains at each Authority Score.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['target', 'target_type'],
            'properties' => [
                ...$this->credentialFields(),
                'target' => Field::text('Target', 'A root domain, a subdomain or a URL.', 'example.com'),
                'target_type' => $this->targetTypeField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'backlinks_ascore_profile', '', ['target' => $this->requiredString($config, 'target', 'The target')], '/analytics/v1/');
    }
}
