<?php

namespace App\Nodes\Integrations\LinkedIn;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class LinkedInCreateLinkPostNode extends AbstractLinkedInNode
{
    public function type(): string
    {
        return 'linkedin_create_link_post';
    }

    public function name(): string
    {
        return 'LinkedIn: Create Link Post';
    }

    public function description(): string
    {
        return 'Publishes a post that shares a link, with its title and description.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['url'],
            'properties' => [
                ...$this->credentialFields(),
                'url' => Field::text('Link', null, 'https://acme.com/blog/launch'),
                'text' => Field::textarea('Post text'),
                'title' => Field::advanced(Field::text('Link title')),
                'description' => Field::advanced(Field::textarea('Link description')),
                'author' => $this->authorField(),
                'visibility' => Field::advanced(Field::select('Who can see it', ['PUBLIC' => 'Anyone', 'CONNECTIONS' => 'Connections only (not for pages)'], null, 'PUBLIC')),
                'markup' => Field::advanced(Field::boolean('The text is already LinkedIn markup', 'Off escapes special characters such as ( ) # @ so the text shows as typed.', false)),
                'disable_reshare' => Field::advanced(Field::boolean('Stop others resharing it', null, false)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $url = trim((string) ($config['url'] ?? ''));

        if (preg_match('~^https?://[^\s]+$~i', $url) !== 1) {
            throw new InvalidArgumentException('The link must start with http:// or https://.');
        }

        return $this->publish($run, $config, $this->authorUrn($run, $config), $this->commentary($config, required: false), ['article' => array_filter([
            'source' => $url,
            'title' => filled($config['title'] ?? null) ? mb_substr(trim((string) $config['title']), 0, 400) : null,
            'description' => filled($config['description'] ?? null) ? mb_substr(trim((string) $config['description']), 0, 4086) : null,
        ], fn (mixed $value): bool => $value !== null)]);
    }
}
