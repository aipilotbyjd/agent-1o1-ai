<?php

namespace App\Nodes\Integrations\GoogleDrive;

use App\Models\Runs\Run;

class GoogleDriveGetFileNode extends AbstractGoogleDriveNode
{
    public function type(): string
    {
        return 'google_drive_get_file';
    }

    public function name(): string
    {
        return 'Google Drive: Get File';
    }

    public function description(): string
    {
        return 'Fetches metadata for a single Google Drive file.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['file_id'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'file_id' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, "/files/{$config['file_id']}", $config, [
            'fields' => '*',
        ]);
    }
}
