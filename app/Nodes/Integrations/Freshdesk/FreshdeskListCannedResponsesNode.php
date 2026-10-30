<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskListCannedResponsesNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_canned_responses';
    }

    public function name(): string
    {
        return 'Freshdesk: List Canned Responses';
    }

    public function description(): string
    {
        return 'Lists the canned responses in a folder, with their content.';
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
        $rows = $this->freshdesk($run, $config, 'GET', "/canned_response_folders/{$id}/responses");

        return ['canned_responses' => array_values($rows), 'count' => count($rows)];
    }
}
