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
            [
                'slug' => 'google_drive',
                'name' => 'Google Drive',
                'icon' => 'drive',
                'color' => '#0F9D58',
                'description' => 'List, fetch, and delete files in Google Drive.',
                'sort_order' => 4,
            ],
            [
                'slug' => 'google_sheets',
                'name' => 'Google Sheets',
                'icon' => 'sheets',
                'color' => '#0F9D58',
                'description' => 'Read, append, and update values in Google Sheets.',
                'sort_order' => 5,
            ],
            [
                'slug' => 'google_docs',
                'name' => 'Google Docs',
                'icon' => 'docs',
                'color' => '#4285F4',
                'description' => 'Create and edit Google Docs.',
                'sort_order' => 6,
            ],
            [
                'slug' => 'google_calendar',
                'name' => 'Google Calendar',
                'icon' => 'calendar',
                'color' => '#4285F4',
                'description' => 'List, create, and delete events on Google Calendar.',
                'sort_order' => 7,
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
