<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brand
    |--------------------------------------------------------------------------
    |
    | The personal assistant's public identity. This is the only place the
    | brand name may appear — code, tables, routes and prompts stay neutral
    | ("assistant") and read it through `BrandRepository`, so a rename is an
    | `.env` change. See docs/ASSISTANT_BRANDING_PLAN.md.
    |
    | Feature names may contain `:name`, replaced with the brand name.
    |
    */

    'brand' => [
        'name' => env('ASSISTANT_NAME', 'Orb'),
        'tagline' => env('ASSISTANT_TAGLINE', 'Your personal AI agent'),
        'description' => env('ASSISTANT_DESCRIPTION', 'Reads your apps, preps your day and handles your inbox.'),
        'emoji' => env('ASSISTANT_EMOJI', '🔮'),
        'color' => env('ASSISTANT_COLOR', '#7C3AED'),
        'icon_url' => env('ASSISTANT_ICON_URL', '/brand/assistant/icon.svg'),
        'avatar_url' => env('ASSISTANT_AVATAR_URL', '/brand/assistant/icon.svg'),

        'features' => [
            'daily' => env('ASSISTANT_FEATURE_DAILY', ':name Daily'),
            'inbox' => env('ASSISTANT_FEATURE_INBOX', ':name Inbox'),
            'meeting_prep' => env('ASSISTANT_FEATURE_PREP', ':name Prep'),
            'situations' => env('ASSISTANT_FEATURE_SITUATIONS', 'Situations'),
        ],

        'email_local_part' => env('ASSISTANT_EMAIL', 'orb'),
        'email_aliases' => array_values(array_filter(array_map('trim', explode(',', (string) env('ASSISTANT_EMAIL_ALIASES', ''))))),
        'inbound_domain' => env('ASSISTANT_INBOUND_DOMAIN', 'agent1o1.ai'),
        'sms_signature' => env('ASSISTANT_SMS_SIGNATURE', '— :name'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Runtime limits
    |--------------------------------------------------------------------------
    */

    'limits' => [
        'instructions_max_chars' => 4000,
        'session_title_max_chars' => 120,
        'message_max_chars' => 20000,
        'style_max_chars' => 2000,
        'feedback_comment_max_chars' => 1000,
    ],

    'runtime' => [
        // The model when the owner hasn't picked one: this internal catalog
        // entry's routes (see ModelCatalogSeeder), or — if it has none —
        // the raw provider/model below.
        'catalog' => env('ASSISTANT_MODEL_CATALOG', 'personal-assistant'),
        'provider' => env('ASSISTANT_PROVIDER', 'anthropic'),
        'model' => env('ASSISTANT_MODEL'),

        // Model calls per turn (each tool round trip is one step).
        'max_steps' => (int) env('ASSISTANT_MAX_STEPS', 100),

        // Most recent messages replayed to the model.
        'history_limit' => 60,

        // Characters of an earlier tool result replayed in later turns.
        'replayed_result_chars' => 4000,

        // A running turn older than this no longer holds the session.
        'turn_stale_after_minutes' => 30,

        // See the `redis-assistant` connection in config/queue.php.
        'queue_connection' => env('ASSISTANT_QUEUE_CONNECTION', 'redis-assistant'),
        'queue' => 'ai-assistant',

        // Seconds a turn job may run; must stay below the connection's retry_after.
        'job_timeout' => 1200,
    ],

    'context' => [
        // Fold older messages into a recap once a conversation has more
        // than this many, or once it fills this share of the model's window.
        'compact_after_messages' => 40,
        'compact_at_ratio' => 0.8,

        // Most recent messages always kept word for word.
        'keep_recent_messages' => 12,

        // Used when the model's catalog entry doesn't state its window.
        'default_window_tokens' => 128000,

        // Rough token estimate for the context meter and the threshold.
        'chars_per_token' => 4,
    ],

    'transcription' => [
        'provider' => env('ASSISTANT_TRANSCRIPTION_PROVIDER', 'openai'),
        'model' => env('ASSISTANT_TRANSCRIPTION_MODEL'),
        'max_kilobytes' => 25600,
    ],

    'approvals' => [
        'ttl_minutes' => (int) env('ASSISTANT_APPROVAL_TTL_MINUTES', 1440),
    ],

];
