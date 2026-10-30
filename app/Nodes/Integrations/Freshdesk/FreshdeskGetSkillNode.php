<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskGetSkillNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_get_skill';
    }

    public function name(): string
    {
        return 'Freshdesk: Get Skill';
    }

    public function description(): string
    {
        return 'Reads a skill by id.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['skill_id'],
            'properties' => [
                ...$this->credentialFields(),
                'skill_id' => Field::text('Skill id'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'skill_id', 'The skill');

        return $this->findOne($run, $config, "/admin/skills/{$id}", $id);
    }
}
