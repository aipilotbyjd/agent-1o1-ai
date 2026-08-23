<?php

namespace App\Nodes\Integrations\GoogleDrive;

use App\Models\Runs\Run;

class GoogleDriveDeleteFileNode extends AbstractGoogleDriveNode
{
    public function type(): string
    {
        return 'google_drive_delete_file';
    }

    public function name(): string
    {
        return 'Google Drive: Delete File';
    }

    public function description(): string
    {
        return 'Deletes a file from Google Drive.';
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
        return $this->delete($run, "/files/{$config['file_id']}", $config);
    }
}
