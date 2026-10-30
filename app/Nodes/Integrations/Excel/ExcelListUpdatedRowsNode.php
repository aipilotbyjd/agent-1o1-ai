<?php

namespace App\Nodes\Integrations\Excel;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ExcelListUpdatedRowsNode extends AbstractExcelNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'excel_list_updated_rows';
    }

    public function name(): string
    {
        return 'Excel: List Rows With Changes';
    }

    public function description(): string
    {
        return 'Reads a sheet\'s rows as records keyed by the first row. As a trigger it fires when a row is added or edited.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id', 'worksheet'],
            'properties' => [
                ...$this->workbookFields(),
                'worksheet' => $this->worksheetField(),
                'address' => Field::advanced(Field::text('Range', 'Empty reads the whole sheet. The first row must hold the column names.', 'A1:D100')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return ['rows' => $this->versioned($this->recordsOf($this->usedRange($run, $config)))];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'rows';
    }

    /**
     * A row is a new item each time its cells change: its number and version.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['_row']) ? $item['_row'].':'.($item['_version'] ?? '') : '';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Excel: Row added or updated';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a row is added to a sheet or a cell in a row is edited.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }

    /**
     * Only the last rows, as the trigger reads a whole sheet but acts on its tail.
     */
    public function triggerItems(array $output, array $config): array
    {
        return $this->recentRecords(array_values(array_filter((array) ($output['rows'] ?? []), 'is_array')));
    }
}
