<?php

namespace App\Nodes\Integrations\Excel;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;

class ExcelListUpdatedTableRowsNode extends AbstractExcelNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'excel_list_updated_table_rows';
    }

    public function name(): string
    {
        return 'Excel: List Table Rows With Changes';
    }

    public function description(): string
    {
        return 'Reads a table\'s rows as records keyed by its header. As a trigger it fires when a row is added or edited.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'table'],
            'properties' => [
                ...$this->workbookFields(),
                'table' => $this->tableField(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['rows' => $this->versioned($this->tableRecords($run, $config))];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'rows';
    }

    /**
     * A row is a new item each time its cells change: its place and version.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['_index']) ? $item['_index'].':'.($item['_version'] ?? '') : '';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Excel: Table row added or updated';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a row is added to a table or a cell in a row is edited.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }

    /**
     * Only the last rows, as the trigger reads a whole table but acts on its tail.
     */
    public function triggerItems(array $output, array $config): array
    {
        return $this->recentRecords(array_values(array_filter((array) ($output['rows'] ?? []), 'is_array')));
    }
}
