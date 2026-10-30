<?php

namespace App\Nodes\Integrations\Excel;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class ExcelListTableRowsNode extends AbstractExcelNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'excel_list_table_rows';
    }

    public function name(): string
    {
        return 'Excel: List Table Rows';
    }

    public function description(): string
    {
        return 'Reads a table\'s rows as records keyed by its header. As a trigger it fires when a row is added.';
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
                'limit' => Field::advanced(Field::integer('Limit', 'The most rows to return.', 1000, 1, 100000)),
                'offset' => Field::advanced(Field::integer('Skip', 'Rows to skip first.', 0, 0)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $records = $this->tableRecords($run, $config);
        $page = array_slice($records, max(0, (int) ($config['offset'] ?? 0)), $this->limit($config, 1000, 100000));

        return ['rows' => $page, 'count' => count($page), 'total' => count($records)];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'rows';
    }

    /**
     * A row is known by its place in the table.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['_index'] ?? '');
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Excel: New row in table';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a row is added at the bottom of a table.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 5;
    }

    /**
     * The config a poll reads with.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100000, 'offset' => 0];
    }

    /**
     * Only the last rows, as the trigger reads a whole table but acts on its tail.
     */
    public function triggerItems(array $output, array $config): array
    {
        return $this->recentRecords(array_values(array_filter((array) ($output['rows'] ?? []), 'is_array')));
    }
}
