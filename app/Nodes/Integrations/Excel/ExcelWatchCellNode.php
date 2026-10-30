<?php

namespace App\Nodes\Integrations\Excel;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;

class ExcelWatchCellNode extends AbstractExcelNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'excel_watch_cell';
    }

    public function name(): string
    {
        return 'Excel: Read Cell or Range With Changes';
    }

    public function description(): string
    {
        return 'Reads a cell or a range. As a trigger it fires when a value in it changes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet', 'address'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'address' => $this->addressField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $range = $this->graph($run, $config, 'GET', $this->range($config), ['$select' => 'address,values,text']);

        return ['changes' => [['address' => $range['address'] ?? null, 'values' => $range['values'] ?? null, 'text' => $range['text'] ?? null, '_version' => substr(md5(json_encode($range['values'] ?? null, JSON_THROW_ON_ERROR)), 0, 12)]]];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'changes';
    }

    /**
     * Each new set of values is a new item.
     */
    public function triggerItemId(array $item): string
    {
        return ($item['address'] ?? '').':'.($item['_version'] ?? '');
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Excel: Cell value changed';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a value in a cell or range changes.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }
}
