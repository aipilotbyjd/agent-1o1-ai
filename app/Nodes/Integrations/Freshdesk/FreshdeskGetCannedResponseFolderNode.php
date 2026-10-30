<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetCannedResponseFolderNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_canned_response_folder';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Canned Response Folder';
    }

    public function description(): string
    {
        return 'Reads a canned response folder with the responses in it.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['folder_id'],
            'properties' => [
                ...$this->credentialFields(),
                'folder_id' => Field::dynamic('Folder', 'freshdesk.canned_response_folders'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'folder_id', 'The folder');

        return $this->findOne($run, $config, "/canned_response_folders/{$id}", $id);
    }
}
