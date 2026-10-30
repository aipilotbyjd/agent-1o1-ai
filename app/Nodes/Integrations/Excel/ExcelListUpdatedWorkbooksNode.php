<?php

namespace App\Nodes\Integrations\Excel;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ExcelListUpdatedWorkbooksNode extends AbstractExcelNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'excel_list_updated_workbooks';
    }

    public function name(): string
    {
        return 'Excel: List Workbooks With Changes';
    }

    public function description(): string
    {
        return 'Lists the workbooks of a drive, most recently changed first. As a trigger it fires when a workbook is created or saved.';
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
                'drive_id' => $this->driveField(),
                'limit' => Field::advanced(Field::integer('Limit', 'The most to return.', 100, 1, 5000)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $workbooks = $this->searchWorkbooks($run, $config, '.xlsx', $this->limit($config));

        return ['workbooks' => $workbooks, 'count' => count($workbooks)];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'workbooks';
    }

    /**
     * A workbook is a new item each time it is saved: its id and change time.
     */
    public function triggerItemId(array $item): string
    {
        return isset($item['id']) ? $item['id'].':'.($item['updated_at'] ?? '') : '';
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Excel: Workbook updated';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a workbook in OneDrive or SharePoint is saved.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 15;
    }

    /**
     * The list holds the newest first.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
