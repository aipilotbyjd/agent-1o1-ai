<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class FreshdeskListCannedResponseFoldersNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_canned_response_folders';
    }

    public function name(): string
    {
        return 'Freshdesk: List Canned Response Folders';
    }

    public function description(): string
    {
        return 'Lists the folders of canned responses.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $rows = $this->freshdesk($run, $config, 'GET', '/canned_response_folders');

        return ['folders' => array_values($rows), 'count' => count($rows)];
    }
}
