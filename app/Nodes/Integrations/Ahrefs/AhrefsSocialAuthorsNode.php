<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;

class AhrefsSocialAuthorsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_social_authors';
    }

    public function name(): string
    {
        return 'Ahrefs: Social Media Authors';
    }

    public function description(): string
    {
        return 'The authors of social media posts in Ahrefs.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
            ],
        ];
    }

    protected function endpoint(): array
    {
        return [
            'method' => 'GET',
            'path' => '/social-media/authors',
            'items' => 'authors',
        ];
    }
}
