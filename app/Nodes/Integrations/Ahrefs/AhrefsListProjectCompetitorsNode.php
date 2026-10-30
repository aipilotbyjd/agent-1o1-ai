<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;
use App\Nodes\Support\Field;

class AhrefsListProjectCompetitorsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_list_project_competitors';
    }

    public function name(): string
    {
        return 'Ahrefs: List Project Competitors';
    }

    public function description(): string
    {
        return 'The competitors of a project.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_id'],
            'properties' => [
                ...$this->credentialFields(),
                'project_id' => Field::dynamic('Project', 'ahrefs.projects', 'Pick a project, or enter its id.', '123456'),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/management/project-competitors',
            'items' => 'competitors',
            'query' => ['project_id'],
        ];
    }
}
