<?php

namespace App\Nodes\Integrations\Pinterest;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class PinterestListBoardsNode extends AbstractPinterestNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'pinterest_list_boards';
    }

    public function name(): string
    {
        return 'Pinterest: List Boards';
    }

    public function description(): string
    {
        return 'Lists your boards. As a trigger it starts the flow for each new board. Checked every few minutes.';
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
                'privacy' => Field::advanced(Field::select('Privacy', ['PUBLIC' => 'Public', 'PROTECTED' => 'Protected', 'SECRET' => 'Secret'])),
                'limit' => Field::integer('Max results', null, 50, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $boards = $this->listAll($run, $config, '/boards', ['privacy' => in_array($config['privacy'] ?? null, ['PUBLIC', 'PROTECTED', 'SECRET'], true) ? $config['privacy'] : null], $this->limit($config, 50));

        return ['boards' => array_map($this->shapeBoard(...), $boards), 'count' => count($boards)];
    }

    protected function triggerItemsPath(): ?string
    {
        return 'boards';
    }

    public function triggerItemId(array $item): string
    {
        return (string) ($item['id'] ?? '');
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 250];
    }

    public function triggerListsNewestFirst(): bool
    {
        return false;
    }

    public function triggerName(): string
    {
        return 'Pinterest: New board';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a board is created in Pinterest. Checked every few minutes.';
    }
}
