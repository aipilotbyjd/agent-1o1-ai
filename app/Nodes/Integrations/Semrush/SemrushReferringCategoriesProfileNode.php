<?php

namespace App\Nodes\Integrations\Semrush;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class SemrushReferringCategoriesProfileNode extends AbstractSemrushReportNode
{
    public function type(): string
    {
        return 'semrush_referring_categories_profile';
    }

    public function name(): string
    {
        return 'Semrush: Referring Categories Profile';
    }

    public function description(): string
    {
        return 'The categories the referring domains of a domain belong to.';
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
                'limit' => $this->limitField(),
                'offset' => $this->offsetField(),
                'columns' => $this->columnsField(['category_name', 'rating']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'backlinks_categories_profile', 'category_name,rating', ['target' => $this->requiredString($config, 'target', 'The target')], '/analytics/v1/');
    }
}
