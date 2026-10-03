<?php

return [

    /*
    |--------------------------------------------------------------------------
    | File ingestion
    |--------------------------------------------------------------------------
    |
    | `POST .../knowledge-base` accepts a `file` instead of raw `text` for
    | plain-text-like formats. There is no PDF/Office text-extraction
    | library in this project (CLAUDE.md: no new dependencies without
    | approval), so only formats readable as UTF-8 text directly are
    | supported — Gumloop's PDF/.docx/.pptx/.xlsx file sources are not.
    */

    'max_upload_kilobytes' => (int) env('KNOWLEDGE_BASE_MAX_UPLOAD_KILOBYTES', 5120),

    'allowed_extensions' => ['txt', 'md', 'markdown', 'csv', 'json', 'xml', 'yaml', 'yml', 'html', 'htm'],

    /*
    |--------------------------------------------------------------------------
    | Synced sources
    |--------------------------------------------------------------------------
    |
    | Web pages and connected apps (Drive, Gmail, Outlook, GitHub, Slack)
    | the knowledge base keeps in sync — shared, or private to one member.
    */

    'sources' => [
        'max_per_workspace' => 200,

        // Documents read per sync; the rest come on the next one.
        'max_documents_per_sync' => 50,
        'max_document_chars' => 100000,

        'sync_every_minutes' => 60,
    ],

];
