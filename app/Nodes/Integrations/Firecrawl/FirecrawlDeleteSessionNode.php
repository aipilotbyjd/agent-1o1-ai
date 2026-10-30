<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlDeleteSessionNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_delete_session';
    }

    public function name(): string
    {
        return 'Firecrawl: Delete Browser Session';
    }

    public function description(): string
    {
        return 'Closes a browser session, to release its resources.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['session_id'],
            'properties' => [
                ...$this->credentialFields(),
                'session_id' => Field::text('Session id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'DELETE', '/interact/'.$this->pathId($config, 'session_id', 'The session id'));

        return ['deleted' => true, 'duration_ms' => $answer['sessionDurationMs'] ?? null, 'credits_billed' => $answer['creditsBilled'] ?? null];
    }
}
