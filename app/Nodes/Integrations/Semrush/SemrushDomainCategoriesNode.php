<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushDomainCategoriesNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_domain_categories';
    }

    public function name(): string
    {
        return 'Semrush: Domain Categories';
    }

    public function description(): string
    {
        return 'The categories a domain belongs to.';
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
                'columns' => $this->columnsField(['category_name', 'rating']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'backlinks_categories', 'category_name,rating', ['target' => $this->requiredString($config, 'target', 'The target')], '/analytics/v1/');
    }
}
