<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ExcelRenameWorkbookNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_rename_workbook';
    }

    public function name(): string
    {
        return 'Excel: Rename or Move Workbook';
    }

    public function description(): string
    {
        return 'Renames a workbook, moves it to another folder, or both.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['workbook_id'],
            'properties' => [
                ...$this->workbookFields(),
                'name' => Field::text('New name', 'Empty keeps the name.', 'Q4 Budget.xlsx'),
                'folder_id' => Field::dynamic('Move to folder', 'excel.folders', 'Empty leaves it where it is.', null, uses: ['drive_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $body = array_filter(['name' => filled($config['name'] ?? null) ? (string) $config['name'] : null, 'parentReference' => filled($config['folder_id'] ?? null) ? ['id' => rawurldecode($this->pathId($config, 'folder_id', 'The folder'))] : null]);

        if ($body === []) {
            throw new InvalidArgumentException('Give a new name or a folder.');
        }

        return $this->shapeItem($this->graph($run, $config, 'PATCH', $this->item($config), [], $body));
    }
}
