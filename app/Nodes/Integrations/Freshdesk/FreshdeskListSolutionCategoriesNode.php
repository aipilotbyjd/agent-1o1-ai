<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FreshdeskListSolutionCategoriesNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_solution_categories';
    }

    public function name(): string
    {
        return 'Freshdesk: List Solution Categories';
    }

    public function description(): string
    {
        return 'Lists the knowledge base\'s categories.';
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
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->freshdesk($run, $config, 'GET', '/solutions/categories');

        return ['categories' => array_values($rows), 'count' => count($rows)];
    }
}
