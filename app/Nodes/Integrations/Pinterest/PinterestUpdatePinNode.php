<?php

namespace App\Nodes\Integrations\Pinterest;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class PinterestUpdatePinNode extends AbstractPinterestNode
{
    public function type(): string
    {
        return 'pinterest_update_pin';
    }

    public function name(): string
    {
        return 'Pinterest: Update Pin';
    }

    public function description(): string
    {
        return 'Changes a pin. Only what is filled in changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['pin_id'],
            'properties' => [
                ...$this->credentialFields(),
                'pin_id' => Field::text('Pin id', 'An id from a previous step (a long number).'),
                'title' => Field::text('Title'),
                'description' => Field::textarea('Description'),
                'link' => Field::text('Link'),
                'alt_text' => Field::advanced(Field::text('Alt text')),
                'board_id' => Field::advanced($this->boardField('Move to board')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->numericId($config, 'pin_id', 'The pin');
        $body = array_filter([
            'title' => $this->optionalString($config, 'title', 'The title', 100),
            'description' => $this->optionalString($config, 'description', 'The description', 800),
            'link' => $this->url($config, 'link', 'The link'),
            'alt_text' => $this->optionalString($config, 'alt_text', 'The alt text', 500),
            'board_id' => $this->optionalId($config, 'board_id', 'The board'),
        ], fn (mixed $value): bool => $value !== null);

        if ($body === []) {
            throw new InvalidArgumentException('Change at least one thing.');
        }

        return $this->shapePin($this->pinterest($run, $config, 'PATCH', "/pins/{$id}", [], $body));
    }
}
