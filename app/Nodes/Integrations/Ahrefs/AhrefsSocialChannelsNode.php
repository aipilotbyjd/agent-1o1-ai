<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Enums\Agents\ActionEffect;

class AhrefsSocialChannelsNode extends AbstractAhrefsReportNode
{
    public function type(): string
    {
        return 'ahrefs_social_channels';
    }

    public function name(): string
    {
        return 'Ahrefs: Social Media Channels';
    }

    public function description(): string
    {
        return 'The social media channels connected to Ahrefs.';
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
            'path' => '/social-media/channels',
            'items' => 'channels',
        ];
    }
}
