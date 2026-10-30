<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskUpdateSolutionFolderNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_update_solution_folder';
    }

    public function name(): string
    {
        return 'Freshdesk: Update Solution Folder';
    }

    public function description(): string
    {
        return 'Changes a knowledge base folder.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['folder_id'],
            'properties' => [
                ...$this->credentialFields(),
                'folder_id' => Field::text('Folder id'),
                'name' => Field::advanced(Field::text('Name')),
                'description' => Field::advanced(Field::textarea('Description')),
                'visibility' => Field::advanced(Field::select('Visible to', ['1' => 'Everyone', '2' => 'Logged in users', '3' => 'Agents', '4' => 'Selected companies'])),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'folder_id', 'The folder');
        $body = $this->present(['name' => $this->text($config, 'name'), 'description' => $this->text($config, 'description'), 'visibility' => $this->number($config, 'visibility')]);
        if ($body === []) {
            throw new \InvalidArgumentException('Fill in something to change.');
        }

        return $this->freshdesk($run, $config, 'PUT', "/solutions/folders/{$id}", [], $body);
    }
}
