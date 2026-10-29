<?php

namespace Database\Seeders;

use App\Models\Nodes\NodeCategory;
use Illuminate\Database\Seeder;

/**
 * One row per integration family (`kind: 'app'`); separate from
 * `NodeCategorySeeder`'s core, built-in categories, since this file is
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
                'icon' => 'mail-01',
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
                'icon' => 'google-drive',
                'color' => '#0F9D58',
                'description' => 'List, fetch, and delete files in Google Drive.',
                'sort_order' => 4,
            ],
            [
                'slug' => 'google_sheets',
                'name' => 'Google Sheets',
                'icon' => 'google-sheet',
                'color' => '#0F9D58',
                'description' => 'Read, append, and update values in Google Sheets.',
                'sort_order' => 5,
            ],
            [
                'slug' => 'google_docs',
                'name' => 'Google Docs',
                'icon' => 'google-doc',
                'color' => '#4285F4',
                'description' => 'Create and edit Google Docs.',
                'sort_order' => 6,
            ],
            [
                'slug' => 'google_calendar',
                'name' => 'Google Calendar',
                'icon' => 'calendar-03',
                'color' => '#4285F4',
                'description' => 'List, create, and delete events on Google Calendar.',
                'sort_order' => 7,
            ],
            [
                'slug' => 'google_tasks',
                'name' => 'Google Tasks',
                'icon' => 'task-01',
                'color' => '#4285F4',
                'description' => 'List, create, update, and complete tasks in Google Tasks.',
                'sort_order' => 9,
            ],
            [
                'slug' => 'google_contacts',
                'name' => 'Google Contacts',
                'icon' => 'contact-01',
                'color' => '#4285F4',
                'description' => 'Search, create, update, and delete contacts in Google Contacts.',
                'sort_order' => 10,
            ],
            [
                'slug' => 'google_forms',
                'name' => 'Google Forms',
                'icon' => 'google-form',
                'color' => '#673AB7',
                'description' => 'Read forms and their responses, and create new forms.',
                'sort_order' => 11,
            ],
            [
                'slug' => 'google_slides',
                'name' => 'Google Slides',
                'icon' => 'google-slide',
                'color' => '#F4B400',
                'description' => 'Create presentations, add slides, and fill in templates in Google Slides.',
                'sort_order' => 12,
            ],
            [
                'slug' => 'google_youtube',
                'name' => 'YouTube',
                'icon' => 'youtube',
                'color' => '#FF0000',
                'description' => 'Search videos, read channels and playlists, and watch for new videos and comments.',
                'sort_order' => 13,
            ],
            [
                'slug' => 'google_analytics',
                'name' => 'Google Analytics',
                'icon' => 'analytics-01',
                'color' => '#F9AB00',
                'description' => 'Run GA4 reports and see who is on your site right now.',
                'sort_order' => 14,
            ],
            [
                'slug' => 'google_search_console',
                'name' => 'Google Search Console',
                'icon' => 'search-01',
                'color' => '#4285F4',
                'description' => 'Read search performance, sitemaps, and index status for your sites.',
                'sort_order' => 15,
            ],
            [
                'slug' => 'outlook',
                'name' => 'Outlook',
                'icon' => 'mail-01',
                'color' => '#0078D4',
                'description' => 'Read, draft and send email, and manage your calendar in Outlook (Microsoft 365).',
                'sort_order' => 8,
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
