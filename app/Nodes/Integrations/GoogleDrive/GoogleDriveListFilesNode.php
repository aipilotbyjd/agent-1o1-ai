<?php

namespace App\Nodes\Integrations\GoogleDrive;

use App\Models\Runs\Run;

class GoogleDriveListFilesNode extends AbstractGoogleDriveNode
{
    public function type(): string
    {
        return 'google_drive_list_files';
    }

    public function name(): string
    {
        return 'Google Drive: List Files';
    }

    public function description(): string
    {
        return 'Lists files in Google Drive matching a query.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'query' => ['type' => 'string'],
                'page_size' => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get($run, '/files', $config, [
            'q' => $config['query'] ?? '',
            'pageSize' => $config['page_size'] ?? 10,
        ]);
    }
}
