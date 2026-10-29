<?php

namespace Database\Seeders;

use App\Enums\Connectors\ConnectorAuthType;
use App\Enums\Connectors\ConnectorCategory;
use App\Models\Connectors\Connector;
use Illuminate\Database\Seeder;

/**
 * The integration catalog backing `ConnectorCredential`. `key` matches the
 * `node_categories.slug` seeded by `AppNodeCategorySeeder` for the same
 * integration, so a node's `category()` doubles as its connector lookup key.
 *
 * Every Google product is its own connector: its own "Connect" button, its
 * own consent screen, its own scope, rather than one shared Google login
 * covering all of them. Matches how Gumloop (and most workflow tools) model
 * Google integrations: users grant only what a given product needs.
 */
class ConnectorSeeder extends Seeder
{
    private const string GOOGLE_AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const string GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /**
     * Without these Google issues no refresh token, and the connection dies
     * with its first access token an hour later.
     */
    private const array GOOGLE_AUTHORIZE_PARAMS = ['access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'true'];

    private const string MICROSOFT_AUTHORIZE_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';

    private const string MICROSOFT_TOKEN_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';

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
                    'category' => $connector['category'],
                    'is_featured' => $connector['is_featured'],
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
     *     category: ConnectorCategory,
     *     is_featured: bool,
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
                'category' => ConnectorCategory::Developer,
                'is_featured' => true,
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => 'https://github.com/login/oauth/authorize',
                    'token_url' => 'https://github.com/login/oauth/access_token',
                    // repo: code, issues, pull requests and webhooks. workflow: run Actions workflows.
                    // read:org: organizations and teams. gist: create gists. notifications: read them.
                    'scopes' => ['repo', 'workflow', 'read:org', 'gist', 'notifications'],
                ],
                'sort_order' => 1,
            ],
            [
                'key' => 'slack',
                'name' => 'Slack',
                'description' => 'Post messages, manage channels, and look up users in Slack.',
                'icon' => 'slack',
                'color' => '#4A154B',
                'category' => ConnectorCategory::Communication,
                'is_featured' => true,
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => 'https://slack.com/oauth/v2/authorize',
                    'token_url' => 'https://slack.com/api/oauth.v2.access',
                    // Everything the Slack nodes and triggers call: posting and editing, direct
                    // messages, channel management, reactions, pins, files, people, mentions.
                    'scopes' => [
                        'chat:write', 'chat:write.public', 'app_mentions:read',
                        'channels:read', 'channels:history', 'channels:join', 'channels:manage',
                        'groups:read', 'groups:history', 'groups:write',
                        'im:write', 'im:read', 'im:history', 'mpim:write',
                        'reactions:read', 'reactions:write', 'pins:read', 'pins:write',
                        'files:read', 'files:write', 'users:read', 'users:read.email',
                    ],
                ],
                'sort_order' => 2,
            ],
            [
                'key' => 'gmail',
                'name' => 'Gmail',
                'description' => 'Send, read, and organize email in Gmail.',
                'icon' => 'mail',
                'color' => '#EA4335',
                'category' => ConnectorCategory::Communication,
                'is_featured' => true,
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => self::GOOGLE_AUTHORIZE_URL,
                    'token_url' => self::GOOGLE_TOKEN_URL,
                    'authorize_params' => self::GOOGLE_AUTHORIZE_PARAMS,
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
                'category' => ConnectorCategory::Storage,
                'is_featured' => false,
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => self::GOOGLE_AUTHORIZE_URL,
                    'token_url' => self::GOOGLE_TOKEN_URL,
                    'authorize_params' => self::GOOGLE_AUTHORIZE_PARAMS,
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
                'category' => ConnectorCategory::Productivity,
                'is_featured' => true,
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => self::GOOGLE_AUTHORIZE_URL,
                    'token_url' => self::GOOGLE_TOKEN_URL,
                    'authorize_params' => self::GOOGLE_AUTHORIZE_PARAMS,
                    'scopes' => ['https://www.googleapis.com/auth/spreadsheets', 'https://www.googleapis.com/auth/drive.metadata.readonly'],
                ],
                'sort_order' => 5,
            ],
            [
                'key' => 'google_docs',
                'name' => 'Google Docs',
                'description' => 'Create and edit Google Docs.',
                'icon' => 'docs',
                'color' => '#4285F4',
                'category' => ConnectorCategory::Productivity,
                'is_featured' => false,
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => self::GOOGLE_AUTHORIZE_URL,
                    'token_url' => self::GOOGLE_TOKEN_URL,
                    'authorize_params' => self::GOOGLE_AUTHORIZE_PARAMS,
                    'scopes' => ['https://www.googleapis.com/auth/documents', 'https://www.googleapis.com/auth/drive.metadata.readonly'],
                ],
                'sort_order' => 6,
            ],
            [
                'key' => 'google_calendar',
                'name' => 'Google Calendar',
                'description' => 'List, create, and delete events on Google Calendar.',
                'icon' => 'calendar',
                'color' => '#4285F4',
                'category' => ConnectorCategory::Productivity,
                'is_featured' => false,
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => self::GOOGLE_AUTHORIZE_URL,
                    'token_url' => self::GOOGLE_TOKEN_URL,
                    'authorize_params' => self::GOOGLE_AUTHORIZE_PARAMS,
                    'scopes' => ['https://www.googleapis.com/auth/calendar'],
                ],
                'sort_order' => 7,
            ],
            [
                'key' => 'google_tasks',
                'name' => 'Google Tasks',
                'description' => 'List, create, update, and complete tasks in Google Tasks.',
                'icon' => 'tasks',
                'color' => '#4285F4',
                'category' => ConnectorCategory::Productivity,
                'is_featured' => false,
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => self::GOOGLE_AUTHORIZE_URL,
                    'token_url' => self::GOOGLE_TOKEN_URL,
                    'authorize_params' => self::GOOGLE_AUTHORIZE_PARAMS,
                    'scopes' => ['https://www.googleapis.com/auth/tasks'],
                ],
                'sort_order' => 9,
            ],
            [
                'key' => 'google_contacts',
                'name' => 'Google Contacts',
                'description' => 'Search, create, update, and delete contacts in Google Contacts.',
                'icon' => 'contacts',
                'color' => '#4285F4',
                'category' => ConnectorCategory::Productivity,
                'is_featured' => false,
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => self::GOOGLE_AUTHORIZE_URL,
                    'token_url' => self::GOOGLE_TOKEN_URL,
                    'authorize_params' => self::GOOGLE_AUTHORIZE_PARAMS,
                    'scopes' => ['https://www.googleapis.com/auth/contacts'],
                ],
                'sort_order' => 10,
            ],
            [
                'key' => 'google_forms',
                'name' => 'Google Forms',
                'description' => 'Read forms and their responses, and create new forms.',
                'icon' => 'forms',
                'color' => '#673AB7',
                'category' => ConnectorCategory::Productivity,
                'is_featured' => false,
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => self::GOOGLE_AUTHORIZE_URL,
                    'token_url' => self::GOOGLE_TOKEN_URL,
                    'authorize_params' => self::GOOGLE_AUTHORIZE_PARAMS,
                    'scopes' => ['https://www.googleapis.com/auth/forms.body', 'https://www.googleapis.com/auth/forms.responses.readonly', 'https://www.googleapis.com/auth/drive.metadata.readonly'],
                ],
                'sort_order' => 11,
            ],
            [
                'key' => 'google_slides',
                'name' => 'Google Slides',
                'description' => 'Create presentations, add slides, and fill in templates in Google Slides.',
                'icon' => 'slides',
                'color' => '#F4B400',
                'category' => ConnectorCategory::Productivity,
                'is_featured' => false,
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => self::GOOGLE_AUTHORIZE_URL,
                    'token_url' => self::GOOGLE_TOKEN_URL,
                    'authorize_params' => self::GOOGLE_AUTHORIZE_PARAMS,
                    'scopes' => ['https://www.googleapis.com/auth/presentations', 'https://www.googleapis.com/auth/drive.metadata.readonly'],
                ],
                'sort_order' => 12,
            ],
            [
                'key' => 'google_youtube',
                'name' => 'YouTube',
                'description' => 'Search videos, read channels and playlists, and watch for new videos and comments.',
                'icon' => 'youtube',
                'color' => '#FF0000',
                'category' => ConnectorCategory::Marketing,
                'is_featured' => false,
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => self::GOOGLE_AUTHORIZE_URL,
                    'token_url' => self::GOOGLE_TOKEN_URL,
                    'authorize_params' => self::GOOGLE_AUTHORIZE_PARAMS,
                    'scopes' => ['https://www.googleapis.com/auth/youtube.readonly'],
                ],
                'sort_order' => 13,
            ],
            [
                'key' => 'google_analytics',
                'name' => 'Google Analytics',
                'description' => 'Run GA4 reports and see who is on your site right now.',
                'icon' => 'analytics',
                'color' => '#F9AB00',
                'category' => ConnectorCategory::Marketing,
                'is_featured' => false,
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => self::GOOGLE_AUTHORIZE_URL,
                    'token_url' => self::GOOGLE_TOKEN_URL,
                    'authorize_params' => self::GOOGLE_AUTHORIZE_PARAMS,
                    'scopes' => ['https://www.googleapis.com/auth/analytics.readonly'],
                ],
                'sort_order' => 14,
            ],
            [
                'key' => 'google_search_console',
                'name' => 'Google Search Console',
                'description' => 'Read search performance, sitemaps, and index status for your sites.',
                'icon' => 'search',
                'color' => '#4285F4',
                'category' => ConnectorCategory::Marketing,
                'is_featured' => false,
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => self::GOOGLE_AUTHORIZE_URL,
                    'token_url' => self::GOOGLE_TOKEN_URL,
                    'authorize_params' => self::GOOGLE_AUTHORIZE_PARAMS,
                    'scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
                ],
                'sort_order' => 15,
            ],
            [
                // Mail and calendar in one connection: Microsoft grants
                // them on one consent screen.
                'key' => 'outlook',
                'name' => 'Outlook',
                'description' => 'Read, draft and send email, and manage your calendar in Outlook (Microsoft 365).',
                'icon' => 'mail',
                'color' => '#0078D4',
                'category' => ConnectorCategory::Communication,
                'is_featured' => false,
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => self::MICROSOFT_AUTHORIZE_URL,
                    'token_url' => self::MICROSOFT_TOKEN_URL,
                    'scopes' => ['offline_access', 'User.Read', 'Mail.ReadWrite', 'Mail.Send', 'MailboxSettings.ReadWrite', 'Calendars.ReadWrite'],
                ],
                'sort_order' => 8,
            ],
            [
                'key' => 'notion',
                'name' => 'Notion',
                'description' => 'Create and update pages and database items, query databases, and work with content, comments and people in Notion.',
                'icon' => 'notion',
                'color' => '#000000',
                'category' => ConnectorCategory::Productivity,
                'is_featured' => true,
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => 'https://api.notion.com/v1/oauth/authorize',
                    'token_url' => 'https://api.notion.com/v1/oauth/token',
                    // Notion takes the client id and secret as Basic auth, and has no scopes:
                    // access is whichever pages the person shares on Notion's own page.
                    'token_auth' => 'basic',
                    'authorize_params' => ['owner' => 'user'],
                    'scopes' => [],
                ],
                'sort_order' => 9,
            ],
            [
                'key' => 'linear',
                'name' => 'Linear',
                'description' => 'Create, search and update issues, projects and initiatives, relate issues, comment, and post project updates in Linear.',
                'icon' => 'linear',
                'color' => '#5E6AD2',
                'category' => ConnectorCategory::Productivity,
                'is_featured' => true,
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => 'https://linear.app/oauth/authorize',
                    'token_url' => 'https://api.linear.app/oauth/token',
                    // Linear takes its scopes comma-separated. `read` and `write` cover every node;
                    // `issues:create` and `comments:create` let a member who can't edit still file and reply.
                    'scope_separator' => ',',
                    'scopes' => [
                        'read', 'write', 'issues:create', 'comments:create',
                        // Creating a webhook through the API needs `admin`, which is also
                        // broad: only asked for while instant delivery is on.
                        ...(config('triggers.push.linear.enabled') ? ['admin'] : []),
                    ],
                ],
                'sort_order' => 17,
            ],
            [
                // One Atlassian login reaches every Jira site the person belongs to; the nodes
                // pick the site (`site`) from the account's accessible resources.
                'key' => 'jira',
                'name' => 'Jira',
                'description' => 'Create, search, update and transition issues, manage projects, comments, attachments, links, users and groups, and Service Management requests in Jira Cloud.',
                'icon' => 'jira',
                'color' => '#0052CC',
                'category' => ConnectorCategory::Developer,
                'is_featured' => true,
                'auth_type' => ConnectorAuthType::OAuth2,
                'fields' => [],
                'oauth' => [
                    'authorize_url' => 'https://auth.atlassian.com/authorize',
                    'token_url' => 'https://auth.atlassian.com/oauth/token',
                    // Atlassian wants the client id and secret in a JSON body, an `audience`, and a
                    // consent screen every time, or it issues no refresh token (`offline_access`
                    // is also needed for one).
                    'token_auth' => 'json',
                    'authorize_params' => ['audience' => 'api.atlassian.com', 'prompt' => 'consent'],
                    'scopes' => [
                        'offline_access',
                        'read:jira-work', 'write:jira-work', 'read:jira-user', 'manage:jira-project',
                        'manage:jira-configuration',
                        'read:servicedesk-request', 'write:servicedesk-request', 'manage:servicedesk-customer',
                        // Registering a webhook; only asked for while instant delivery is on.
                        ...(config('triggers.push.jira.enabled') ? ['manage:jira-webhook'] : []),
                    ],
                ],
                'sort_order' => 18,
            ],
        ];
    }
}
