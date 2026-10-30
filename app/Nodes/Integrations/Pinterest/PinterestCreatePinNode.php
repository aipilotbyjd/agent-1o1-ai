<?php

namespace App\Nodes\Integrations\Pinterest;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class PinterestCreatePinNode extends AbstractPinterestNode
{
    public function type(): string
    {
        return 'pinterest_create_pin';
    }

    public function name(): string
    {
        return 'Pinterest: Create Pin';
    }

    public function description(): string
    {
        return 'Creates a pin on a board from an image address.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::External;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['board_id', 'image_url'],
            'properties' => [
                ...$this->credentialFields(),
                'board_id' => $this->boardField(),
                'section_id' => Field::advanced(Field::dynamic('Section', 'pinterest.sections', 'A section of the board.', 'Weeknight dinners', dependsOn: ['board_id'])),
                'image_url' => Field::text('Image address', 'A public JPEG or PNG.', 'https://acme.com/recipe.jpg'),
                'title' => Field::text('Title', 'Up to 100 characters.'),
                'description' => Field::textarea('Description', 'Up to 800 characters.'),
                'link' => Field::text('Link', 'Where the pin leads.', 'https://acme.com/recipes/pasta'),
                'alt_text' => Field::advanced(Field::text('Alt text', 'Up to 500 characters.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $image = $this->url($config, 'image_url', 'The image address') ?? throw new InvalidArgumentException('The image address is required.');

        $pin = $this->pinterest($run, $config, 'POST', '/pins', [], [
            'board_id' => $this->numericId($config, 'board_id', 'The board'),
            'board_section_id' => $this->optionalId($config, 'section_id', 'The section'),
            'title' => $this->optionalString($config, 'title', 'The title', 100),
            'description' => $this->optionalString($config, 'description', 'The description', 800),
            'link' => $this->url($config, 'link', 'The link'),
            'alt_text' => $this->optionalString($config, 'alt_text', 'The alt text', 500),
            'media_source' => ['source_type' => 'image_url', 'url' => $image],
        ]);

        return $this->shapePin($pin);
    }
}
