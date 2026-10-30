<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetCannedResponseNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_canned_response';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Canned Response';
    }

    public function description(): string
    {
        return 'Reads a canned response.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['canned_response_id'],
            'properties' => [
                ...$this->credentialFields(),
                'canned_response_id' => Field::text('Canned response id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'canned_response_id', 'The canned response');

        return $this->findOne($run, $config, "/canned_responses/{$id}", $id);
    }
}
