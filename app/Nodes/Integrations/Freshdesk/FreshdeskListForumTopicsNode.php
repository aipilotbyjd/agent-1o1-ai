<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskListForumTopicsNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_list_forum_topics';
    }

    public function name(): string
    {
        return 'Freshdesk: List Forum Topics';
    }

    public function description(): string
    {
        return 'Lists the topics of a forum.';
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
                'limit' => Field::integer('Max results', null, 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'forum_id', 'The forum');

        return $this->listing($run, $config, "/discussions/forums/{$id}/topics", 'topics');
    }
}
