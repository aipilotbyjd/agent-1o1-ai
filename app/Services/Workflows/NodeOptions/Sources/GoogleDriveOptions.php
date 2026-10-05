<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use App\Services\Workflows\NodeOptions\Sources\Concerns\ListsDriveFiles;

/**
 * Any Drive file, newest first.
 */
class GoogleDriveOptions extends HttpOptionsSource
{
    use ListsDriveFiles;

    public function sources(): array
    {
        return ['google_drive.files'];
    }

    protected function appName(): string
    {
        return 'Google Drive';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return $this->driveFiles($query);
    }
}
