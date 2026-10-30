<?php

namespace App\Nodes\Integrations\Firecrawl;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FirecrawlCreateSessionNode extends AbstractFirecrawlNode
{
    public function type(): string
    {
        return 'firecrawl_create_session';
    }

    public function name(): string
    {
        return 'Firecrawl: Create Browser Session';
    }

    public function description(): string
    {
        return 'Starts a standalone browser session to run code in, and returns its id and a live view.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                'ttl' => Field::integer('Lifetime (seconds)', null, 300, 1),
                'activity_ttl' => Field::advanced(Field::integer('Close when idle for (seconds)', null, null, 1)),
                'block_ads' => Field::advanced(Field::boolean('Block ads', null, true)),
                'profile' => Field::advanced(Field::json('Saved profile', 'Keeps sign-ins between sessions, as in Firecrawl\'s docs.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->firecrawl($run, $config, 'POST', '/interact', $this->withoutEmpty([
            'ttl' => $this->intOf($config, 'ttl'),
            'activityTtl' => $this->intOf($config, 'activity_ttl'),
            'blockAds' => $this->boolOf($config, 'block_ads'),
            'profile' => $this->decoded($config['profile'] ?? null, 'The profile'),
        ]));

        return ['id' => $answer['id'] ?? null, 'live_view_url' => $answer['liveViewUrl'] ?? null, 'interactive_live_view_url' => $answer['interactiveLiveViewUrl'] ?? null, 'expires_at' => $answer['expiresAt'] ?? null];
    }
}
