<?php

namespace App\Nodes\Integrations\Excel;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;

class ExcelListWorksheetsNode extends AbstractExcelNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'excel_list_worksheets';
    }

    public function name(): string
    {
        return 'Excel: List Worksheets';
    }

    public function description(): string
    {
        return 'Lists the sheets of a workbook. As a trigger it fires when a sheet is added.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id'],
            'properties' => [
                ...$this->workbookFields(),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $sheets = array_map($this->shapeWorksheet(...), array_values(array_filter((array) ($this->graph($run, $config, 'GET', $this->workbook($config).'/worksheets')['value'] ?? []), 'is_array')));

        return ['worksheets' => $sheets, 'count' => count($sheets)];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'worksheets';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Excel: New worksheet';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a sheet is added to the workbook.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 15;
    }
}
