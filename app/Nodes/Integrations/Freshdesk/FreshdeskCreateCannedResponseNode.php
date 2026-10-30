<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskCreateCannedResponseNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_create_canned_response';
    }

    public function name(): string
    {
        return 'Freshdesk: Create Canned Response';
    }

    public function description(): string
    {
        return 'Adds a canned response to a folder.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['title', 'content_html', 'folder_id'],
            'properties' => [
                ...$this->credentialFields(),
                'title' => Field::text('Title'),
                'content_html' => Field::textarea('Content', 'HTML is kept.'),
                'folder_id' => Field::dynamic('Folder', 'freshdesk.canned_response_folders'),
                'visibility' => Field::advanced(Field::select('Visible to', ['0' => 'All agents', '1' => 'Only me', '2' => 'Selected groups'], null, '0')),
                'group_ids' => Field::advanced(Field::text('Group ids', 'Separated by commas, for selected groups.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->freshdesk($run, $config, 'POST', '/canned_responses', [], $this->present(['title' => $this->requiredString($config, 'title', 'The title'), 'content_html' => $this->requiredString($config, 'content_html', 'The content'), 'folder_id' => (int) $this->numericId($config, 'folder_id', 'The folder'), 'visibility' => $this->number($config, 'visibility') ?? 0, 'group_ids' => $this->idList($config, 'group_ids', 'The groups', false)]));
    }
}
