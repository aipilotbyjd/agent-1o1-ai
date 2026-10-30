<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateForumNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_forum';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Forum';
    }

    public function description(): string
    {
        return 'Changes a forum.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['forum_id'],
            'properties' => [
                ...$this->credentialFields(),
                'forum_id' => Field::text('Forum id'),
                'name' => Field::advanced(Field::text('Name')),
                'forum_type' => Field::advanced(Field::select('Type', ['1' => 'How-to', '2' => 'Ideas', '3' => 'Problems', '4' => 'Announcements'])),
                'forum_visibility' => Field::advanced(Field::select('Visible to', ['1' => 'Everyone', '2' => 'Logged in users', '3' => 'Agents', '4' => 'Selected companies'])),
                'description' => Field::advanced(Field::textarea('Description')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'forum_id', 'The forum');
        $body = $this->present(['name' => $this->text($config, 'name'), 'forum_type' => $this->number($config, 'forum_type'), 'forum_visibility' => $this->number($config, 'forum_visibility'), 'description' => $this->text($config, 'description')]);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->freshdesk($run, $config, 'PUT', "/discussions/forums/{$id}", [], $body);
    }
}
