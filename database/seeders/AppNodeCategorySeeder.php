<?php

namespace Database\Seeders;

use App\Models\Nodes\NodeCategory;
use Illuminate\Database\Seeder;

/**
 * One row per integration family (`kind: 'app'`) — separate from
 * `NodeCategorySeeder`'s five core, built-in categories, since this file is
 * the one that grows with every new Stage 11 integration
 * (docs/WORKFLOWS_AGENTS_BUILD_PLAN.md), while the core list never changes.
 */
class AppNodeCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug' => 'slack',
                'name' => 'Slack',
                'icon' => 'slack',
                'color' => '#4A154B',
                'description' => 'Post messages, manage channels, and look up users in Slack.',
                'sort_order' => 1,
            ],
            [
                'slug' => 'gmail',
                'name' => 'Gmail',
                'icon' => 'mail',
                'color' => '#EA4335',
                'description' => 'Send, read, and organize email in Gmail.',
                'sort_order' => 2,
            ],
            [
                'slug' => 'github',
                'name' => 'GitHub',
                'icon' => 'github',
                'color' => '#24292E',
                'description' => 'Manage repositories, issues, pull requests, and commits on GitHub.',
                'sort_order' => 3,
            ],
        ];

        foreach ($categories as $category) {
            NodeCategory::query()->updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'icon' => $category['icon'],
                    'color' => $category['color'],
                    'sort_order' => $category['sort_order'],
                    'kind' => 'app',
                ],
            );
        }
    }
}
