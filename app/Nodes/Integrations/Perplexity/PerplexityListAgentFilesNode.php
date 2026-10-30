<?php

namespace App\Nodes\Integrations\Perplexity;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class PerplexityListAgentFilesNode extends AbstractPerplexityNode
{
    public function type(): string
    {
        return 'perplexity_list_agent_files';
    }

    public function name(): string
    {
        return 'Perplexity: List Agent Response Files';
    }

    public function description(): string
    {
        return 'Lists the files a response made.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['response_id'],
            'properties' => [
                ...$this->credentialFields(),
                'response_id' => Field::text('Response id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $files = array_values((array) ($this->perplexity($run, $config, 'GET', '/v1/agent/'.$this->pathId($config, 'response_id', 'The response id').'/files')['data'] ?? []));

        return ['files' => $files, 'count' => count($files)];
    }
}
