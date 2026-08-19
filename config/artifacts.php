<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage disk
    |--------------------------------------------------------------------------
    |
    | Where artifact bytes live — both agent-exported files
    | (App\Ai\Tools\ExportArtifactTool) and member uploads. Point this at an
    | S3-compatible disk in production; `Artifact.disk` records the disk each
    | row was written to, so changing this never orphans existing files.
    */

    'disk' => env('ARTIFACTS_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Upload limit
    |--------------------------------------------------------------------------
    |
    | Maximum size of a single uploaded artifact, in kilobytes. PHP's own
    | `upload_max_filesize`/`post_max_size` still apply and must be at least
    | this large, or the request never reaches validation.
    */

    'max_upload_kilobytes' => (int) env('ARTIFACTS_MAX_UPLOAD_KILOBYTES', 25600),

];
