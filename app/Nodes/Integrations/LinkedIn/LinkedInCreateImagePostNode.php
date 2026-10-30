<?php

namespace App\Nodes\Integrations\LinkedIn;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class LinkedInCreateImagePostNode extends AbstractLinkedInNode
{
    public function type(): string
    {
        return 'linkedin_create_image_post';
    }

    public function name(): string
    {
        return 'LinkedIn: Create Image Post';
    }

    public function description(): string
    {
        return 'Downloads an image from a public address, uploads it to LinkedIn and publishes it in a post.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['image_url'],
            'properties' => [
                ...$this->credentialFields(),
                'image_url' => Field::text('Image address', 'A public JPEG, PNG or GIF of at most 8 MB.', 'https://acme.com/launch.png'),
                'text' => Field::textarea('Post text'),
                'alt_text' => Field::advanced(Field::text('Alt text')),
                'author' => $this->authorField(),
                'visibility' => Field::advanced(Field::select('Who can see it', ['PUBLIC' => 'Anyone', 'CONNECTIONS' => 'Connections only (not for pages)'], null, 'PUBLIC')),
                'markup' => Field::advanced(Field::boolean('The text is already LinkedIn markup', 'Off escapes special characters such as ( ) # @ so the text shows as typed.', false)),
                'disable_reshare' => Field::advanced(Field::boolean('Stop others resharing it', null, false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $author = $this->authorUrn($run, $config);
        $image = $this->uploadImage($run, $config, $author, trim((string) ($config['image_url'] ?? '')));

        return $this->publish($run, $config, $author, $this->commentary($config, required: false), ['media' => array_filter([
            'id' => $image,
            'altText' => filled($config['alt_text'] ?? null) ? mb_substr(trim((string) $config['alt_text']), 0, 4086) : null,
        ], fn (mixed $value): bool => $value !== null)]);
    }
}
