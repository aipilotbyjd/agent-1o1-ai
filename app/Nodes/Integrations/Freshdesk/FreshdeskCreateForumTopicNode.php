<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskCreateForumTopicNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_forum_topic';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Forum Topic';
    }

    public function description(): string
    {
        return 'Starts a topic in a forum.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['forum_id', 'title', 'message'],
            'properties' => [
                ...$this->credentialFields(),
                'forum_id' => Field::text('Forum id'),
                'title' => Field::text('Title'),
                'message' => Field::textarea('Message', 'HTML is kept.'),
                'sticky' => Field::advanced(Field::boolean('Sticky')),
                'locked' => Field::advanced(Field::boolean('Locked')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'forum_id', 'The forum');

        return $this->freshdesk($run, $config, 'POST', "/discussions/forums/{$id}/topics", [], $this->present(['title' => $this->requiredString($config, 'title', 'The title'), 'message' => $this->requiredString($config, 'message', 'The message'), 'sticky' => $this->flag($config, 'sticky'), 'locked' => $this->flag($config, 'locked')]));
    }
}
