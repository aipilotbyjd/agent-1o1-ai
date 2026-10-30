<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskCreateCannedResponseFolderNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_canned_response_folder';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Canned Response Folder';
    }

    public function description(): string
    {
        return 'Adds a folder of canned responses.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->freshdesk($run, $config, 'POST', '/canned_response_folders', [], ['name' => $this->requiredString($config, 'name', 'The name')]);
    }
}
