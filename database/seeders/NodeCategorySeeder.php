<?php

namespace Database\Seeders;

use App\Enums\NodeCategory as NodeCategoryEnum;
use App\Models\Nodes\NodeCategory;
use Illuminate\Database\Seeder;

/**
 * The core, built-in node categories — one row per `Enums\NodeCategory` case.
 * App/integration categories (`slack`, `github`, ...) are seeded alongside
 * their node families as those land (docs/NODES_CATALOG.md), not here.
 */
class NodeCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'enum' => NodeCategoryEnum::TriggersEvents,
                'icon' => 'zap',
                'color' => '#F59E0B',
                'description' => 'Start a workflow manually, on a schedule, or from a webhook/app event.',
                'sort_order' => 1,
            ],
            [
                'enum' => NodeCategoryEnum::AiAutomation,
                'icon' => 'sparkles',
                'color' => '#8B5CF6',
                'description' => 'Prompt an LLM, extract structured data, or generate media.',
                'sort_order' => 2,
            ],
            [
                'enum' => NodeCategoryEnum::FlowLogic,
                'icon' => 'git-branch',
                'color' => '#3B82F6',
                'description' => 'Branch, loop, merge, wait, and handle errors.',
                'sort_order' => 3,
            ],
            [
                'enum' => NodeCategoryEnum::DataTransform,
                'icon' => 'shuffle',
                'color' => '#10B981',
                'description' => 'Reshape data, call generic APIs, run whitelisted transforms.',
                'sort_order' => 4,
            ],
            [
                'enum' => NodeCategoryEnum::Custom,
                'icon' => 'puzzle',
                'color' => '#6B7280',
                'description' => 'Nodes you define yourself, scoped to your workspace.',
                'sort_order' => 5,
            ],
        ];

        foreach ($categories as $category) {
            NodeCategory::query()->updateOrCreate(
                ['slug' => $category['enum']->value],
                [
                    'name' => $category['enum']->label(),
                    'description' => $category['description'],
                    'icon' => $category['icon'],
                    'color' => $category['color'],
                    'sort_order' => $category['sort_order'],
                    'kind' => 'core',
                ],
            );
        }
    }
}
