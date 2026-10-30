<?php

namespace App\Nodes\Integrations\Pinterest;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class PinterestListPinsNode extends AbstractPinterestNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'pinterest_list_pins';
    }

    public function name(): string
    {
        return 'Pinterest: List Pins';
    }

    public function description(): string
    {
        return 'Lists the pins of a board, or all your pins when none is chosen. As a trigger it starts the flow for each new one. Checked every few minutes.';
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
                'board_id' => Field::advanced($this->boardField()),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $board = $this->optionalId($config, 'board_id', 'The board');
        $pins = $this->listAll($run, $config, $board === null ? '/pins' : "/boards/{$board}/pins", [], $this->limit($config, 50));

        usort($pins, fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return ['pins' => array_map($this->shapePin(...), $pins), 'count' => count($pins)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'pins';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['id'] ?? '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100];
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    public function triggerName(): string
    {
        return 'Pinterest: New pin';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a pin is created on a board, or on your account. Checked every few minutes.';
    }
}
