<?php

namespace App\Nodes\Integrations\Freshdesk;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class FreshdeskDeleteSkillNode extends AbstractFreshdeskNode
{
    public function type(): string
    {
        return 'freshdesk_delete_skill';
    }

    public function name(): string
    {
        return 'Freshdesk: Delete Skill';
    }

    public function description(): string
    {
        return 'Deletes a skill.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Destructive;
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

        return $this->destroy($run, $config, "/admin/skills/{$id}", $id);
    }
}
