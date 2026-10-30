<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetForumNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_forum';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Forum';
    }

    public function description(): string
    {
        return 'Reads a forum.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['forum_id'],
            'properties' => [
                ...$this->credentialFields(),
                'forum_id' => Field::text('Forum id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'forum_id', 'The forum');

        return $this->findOne($run, $config, "/discussions/forums/{$id}", $id);
    }
}
