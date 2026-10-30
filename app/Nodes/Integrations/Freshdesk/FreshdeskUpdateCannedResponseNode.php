<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateCannedResponseNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_canned_response';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Canned Response';
    }

    public function description(): string
    {
        return 'Changes a canned response. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['canned_response_id'],
            'properties' => [
                ...$this->credentialFields(),
                'canned_response_id' => Field::text('Canned response id'),
                'title' => Field::advanced(Field::text('Title')),
                'content_html' => Field::advanced(Field::textarea('Content', 'HTML is kept.')),
                'folder_id' => Field::advanced(Field::dynamic('Folder', 'freshdesk.canned_response_folders')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'canned_response_id', 'The canned response');
        $body = $this->present(['title' => $this->text($config, 'title'), 'content_html' => $this->text($config, 'content_html'), 'folder_id' => $this->optionalId($config, 'folder_id', 'The folder')]);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->freshdesk($run, $config, 'PUT', "/canned_responses/{$id}", [], $body);
    }
}
