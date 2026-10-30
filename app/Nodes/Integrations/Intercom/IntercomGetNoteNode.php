<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomGetNoteNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_get_note';
    }

    public function name(): string
    {
        return 'Intercom: Get Note';
    }

    public function description(): string
    {
        return 'Reads one note.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['note_id'],
            'properties' => [
                ...$this->credentialFields(),
                'note_id' => Field::text('Note id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->safeId($config, 'note_id', 'The note');

        return $this->findOne($run, $config, "/notes/{$id}", $id);
    }
}
