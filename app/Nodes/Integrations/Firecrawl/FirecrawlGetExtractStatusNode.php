<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlGetExtractStatusNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_get_extract_status';
    }

    public function name(): string
    {
        return 'Firecrawl: Get Extract Status';
    }

    public function description(): string
    {
        return 'Reads how far an extract is, and its data once done.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['extract_id'],
            'properties' => [
                ...$this->credentialFields(),
                'extract_id' => Field::text('Extract id', 'The id Start Extract returned.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'extract_id', 'The extract id');
        $answer = $this->firecrawl($run, $config, 'GET', "/extract/{$id}");

        return ['id' => $id, 'status' => $answer['status'] ?? null, 'finished' => in_array($answer['status'] ?? null, ['completed', 'failed', 'cancelled'], true), 'data' => $answer['data'] ?? null, 'sources' => $answer['sources'] ?? null, 'tokens_used' => $answer['tokensUsed'] ?? null, 'expires_at' => $answer['expiresAt'] ?? null];
    }
}
