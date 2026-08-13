<?php

namespace Database\Seeders;

use App\Enums\Connectors\ConnectorAuthType;
use App\Models\Connectors\Connector;
use Illuminate\Database\Seeder;

/**
 * The integration catalog backing `ConnectorCredential`. `key` matches the
 * `node_categories.slug` seeded by `AppNodeCategorySeeder` for the same
 * integration, so a node's `category()` doubles as its connector lookup key.
 */
class ConnectorSeeder extends Seeder
{
    public function run(): void
    {
        $connectors = [
            [
                'key' => 'github',
                'name' => 'GitHub',
                'description' => 'Manage repositories, issues, pull requests, and commits on GitHub.',
                'icon' => 'github',
                'color' => '#24292E',
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => 'https://github.com/login/oauth/authorize',
                    'token_url' => 'https://github.com/login/oauth/access_token',
                    'scopes' => ['repo'],
                ],
                'sort_order' => 1,
            ],
            [
                'key' => 'slack',
                'name' => 'Slack',
                'description' => 'Post messages, manage channels, and look up users in Slack.',
                'icon' => 'slack',
                'color' => '#4A154B',
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => 'https://slack.com/oauth/v2/authorize',
                    'token_url' => 'https://slack.com/api/oauth.v2.access',
                    'scopes' => ['chat:write', 'channels:read', 'users:read'],
                ],
                'sort_order' => 2,
            ],
            [
                'key' => 'gmail',
                'name' => 'Gmail',
                'description' => 'Send, read, and organize email in Gmail.',
                'icon' => 'mail',
                'color' => '#EA4335',
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                    'token_url' => 'https://oauth2.googleapis.com/token',
                    'scopes' => ['https://www.googleapis.com/auth/gmail.modify'],
                ],
                'sort_order' => 3,
            ],
        ];

        foreach ($connectors as $connector) {
            Connector::query()->updateOrCreate(
                ['key' => $connector['key']],
                [
                    'name' => $connector['name'],
                    'description' => $connector['description'],
                    'icon' => $connector['icon'],
                    'color' => $connector['color'],
                    'auth_type' => $connector['auth_type'],
                    'fields' => $connector['fields'],
                    'oauth' => $connector['oauth'],
                    'sort_order' => $connector['sort_order'],
                ],
            );
        }
    }
}
