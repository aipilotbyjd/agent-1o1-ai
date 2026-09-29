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

];
