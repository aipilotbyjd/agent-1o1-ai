<?php

namespace App\Nodes\Integrations\Exa;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExaUpdateWebsetNode extends AbstractExaNode
{
    public function type(): string
    {
        return 'exa_update_webset';
    }

    public function name(): string
    {
        return 'Exa: Update Webset';
    }

    public function description(): string
    {
        return 'Changes a webset\'s title or metadata.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['webset_id'],
            'properties' => [
                ...$this->credentialFields(),
                'webset_id' => $this->websetField(),
                'title' => Field::text('Title'),
                'metadata' => Field::advanced(Field::keyValue('Metadata')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['webset' => $this->exa($run, $config, 'POST', self::WEBSETS.'/websets/'.$this->pathId($config, 'webset_id', 'The webset'), $this->withoutEmpty(['title' => $this->textOf($config, 'title'), 'metadata' => $this->decoded($config['metadata'] ?? null, 'The metadata')]))];
    }
}
