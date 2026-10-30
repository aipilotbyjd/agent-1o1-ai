<?php

namespace App\Nodes\Integrations\Intercom;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class IntercomCreateTagNode extends AbstractIntercomNode
{
    public function type(): string
    {
        return 'intercom_create_tag';
    }

    public function name(): string
    {
        return 'Intercom: Create or Update Tag';
    }

    public function description(): string
    {
        return 'Creates a tag, or renames the tag with this id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'name' => Field::text('Name', null, 'VIP'),
                'id' => Field::advanced(Field::text('Tag id', 'Set this to rename an existing tag.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->intercom($run, $config, 'POST', '/tags', [], $this->present(['name' => $this->requiredString($config, 'name', 'The name'), 'id' => $this->text($config, 'id')]));
    }
}
