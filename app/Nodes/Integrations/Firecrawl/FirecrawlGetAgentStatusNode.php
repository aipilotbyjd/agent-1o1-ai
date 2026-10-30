<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlGetAgentStatusNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_get_agent_status';
    }

    public function name(): string
    {
        return 'Firecrawl: Get Agent Status';
    }

    public function description(): string
    {
        return 'Reads how far an agent run is, and its data once done.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['agent_id'],
            'properties' => [
                ...$this->credentialFields(),
                'agent_id' => Field::text('Agent run id', 'The id Start Agent returned.'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->pathId($config, 'agent_id', 'The agent run id');
        $answer = $this->firecrawl($run, $config, 'GET', "/agent/{$id}");

        return ['id' => $id, 'status' => $answer['status'] ?? null, 'finished' => in_array($answer['status'] ?? null, ['completed', 'failed'], true), 'data' => $answer['data'] ?? null, 'partial' => $answer['partial'] ?? null, 'message' => $answer['message'] ?? null, 'error' => $answer['error'] ?? null, 'credits_used' => $answer['creditsUsed'] ?? null, 'expires_at' => $answer['expiresAt'] ?? null];
    }
}
