<?php

namespace App\Nodes\Integrations\LinkedIn;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInCreateTextPostNode extends AbstractLinkedInNode
{
    public function type(): string
    {
        return 'linkedin_create_text_post';
    }

    public function name(): string
    {
        return 'LinkedIn: Create Text Post';
    }

    public function description(): string
    {
        return 'Publishes a text post as you or as a company page you administer.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['text'],
            'properties' => [
                ...$this->credentialFields(),
                'text' => Field::textarea('Post text', 'Up to 3000 characters.'),
                'author' => $this->authorField(),
                'visibility' => Field::advanced(Field::select('Who can see it', ['PUBLIC' => 'Anyone', 'CONNECTIONS' => 'Connections only (not for pages)'], null, 'PUBLIC')),
                'markup' => Field::advanced(Field::boolean('The text is already LinkedIn markup', 'Off escapes special characters such as ( ) # @ so the text shows as typed.', false)),
                'disable_reshare' => Field::advanced(Field::boolean('Stop others resharing it', null, false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->publish($run, $config, $this->authorUrn($run, $config), $this->commentary($config));
    }
}
