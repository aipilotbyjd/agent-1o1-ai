<?php

namespace Database\Seeders;

use App\Enums\Connectors\ConnectorAuthType;
use App\Models\Connectors\Connector;
use Illuminate\Database\Seeder;

/**
 * The integration catalog backing `ConnectorCredential`. `key` matches the
 * `node_categories.slug` seeded by `AppNodeCategorySeeder` for the same
 * integration, so a node's `category()` doubles as its connector lookup key.
 *
 * Every Google product is its own connector — its own "Connect" button, its
 * own consent screen, its own scope — rather than one shared Google login
 * covering all of them. Matches how Gumloop (and most workflow tools) model
 * Google integrations: users grant only what a given product needs.
 */
class ConnectorSeeder extends Seeder
{
    private const string GOOGLE_AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const string GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function run(): void
    {
        foreach ($this->connectors() as $connector) {
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

    /**
     * @return array<int, array{
     *     key: string,
     *     name: string,
     *     description: string,
     *     icon: string,
     *     color: string,
     *     auth_type: ConnectorAuthType,
     *     fields: array<int, mixed>,
     *     oauth: array{authorize_url: string, token_url: string, scopes: array<int, string>},
     *     sort_order: int,
     * }>
     */
    private function connectors(): array
    {
        return [
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
                    'authorize_url' => self::GOOGLE_AUTHORIZE_URL,
                    'token_url' => self::GOOGLE_TOKEN_URL,
                    'scopes' => ['https://www.googleapis.com/auth/gmail.modify'],
                ],
                'sort_order' => 3,
            ],
            [
                'key' => 'google_drive',
                'name' => 'Google Drive',
                'description' => 'List, fetch, and delete files in Google Drive.',
                'icon' => 'drive',
                'color' => '#0F9D58',
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => self::GOOGLE_AUTHORIZE_URL,
                    'token_url' => self::GOOGLE_TOKEN_URL,
                    'scopes' => ['https://www.googleapis.com/auth/drive'],
                ],
                'sort_order' => 4,
            ],
            [
                'key' => 'google_sheets',
                'name' => 'Google Sheets',
                'description' => 'Read, append, and update values in Google Sheets.',
                'icon' => 'sheets',
                'color' => '#0F9D58',
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => self::GOOGLE_AUTHORIZE_URL,
                    'token_url' => self::GOOGLE_TOKEN_URL,
                    'scopes' => ['https://www.googleapis.com/auth/spreadsheets'],
                ],
                'sort_order' => 5,
            ],
            [
                'key' => 'google_docs',
                'name' => 'Google Docs',
                'description' => 'Create and edit Google Docs.',
                'icon' => 'docs',
                'color' => '#4285F4',
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => self::GOOGLE_AUTHORIZE_URL,
                    'token_url' => self::GOOGLE_TOKEN_URL,
                    'scopes' => ['https://www.googleapis.com/auth/documents'],
                ],
                'sort_order' => 6,
            ],
            [
                'key' => 'google_calendar',
                'name' => 'Google Calendar',
                'description' => 'List, create, and delete events on Google Calendar.',
                'icon' => 'calendar',
                'color' => '#4285F4',
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => self::GOOGLE_AUTHORIZE_URL,
                    'token_url' => self::GOOGLE_TOKEN_URL,
                    'scopes' => ['https://www.googleapis.com/auth/calendar'],
                ],
                'sort_order' => 7,
            ],
        ];
    }
}
