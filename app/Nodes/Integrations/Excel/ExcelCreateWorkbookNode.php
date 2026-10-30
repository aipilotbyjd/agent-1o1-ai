<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ExcelCreateWorkbookNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_create_workbook';
    }

    public function name(): string
    {
        return 'Excel: Create Workbook';
    }

    public function description(): string
    {
        return 'Creates an empty workbook in OneDrive or SharePoint. Graph adds a number to the name when it is taken.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                ...$this->credentialFields(),
                'drive_id' => $this->driveField(),
                'name' => Field::text('Name', 'The file name. .xlsx is added when missing.', 'Q4 Budget'),
                'folder_id' => Field::dynamic('Folder', 'excel.folders', 'Pick a folder, or enter its id. Empty is the top of the drive.', null, uses: ['drive_id']),
                'worksheet_name' => Field::advanced(Field::text('First worksheet name', 'Renames the first sheet, which starts as Sheet1.', 'Sheet1')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $name = $this->requiredString($config, 'name', 'The name');

        if (preg_match('/["*:<>?\/\\\\|\x00-\x1F]/', $name) === 1) {
            throw new InvalidArgumentException('A file name can\'t hold " * : < > ? / \\ or |.');
        }

        $name = preg_match('/\.xlsx$/i', $name) === 1 ? $name : "{$name}.xlsx";
        $parent = filled($config['folder_id'] ?? null) ? $this->item($config, 'folder_id', 'The folder').':/' : $this->drive($config).'/root:/';
        $item = $this->uploadBlankWorkbook($run, $config, $parent.rawurlencode($name).':/content');

        if (filled($config['worksheet_name'] ?? null)) {
            $this->graph($run, $config, 'PATCH', $this->drive($config).'/items/'.rawurlencode((string) $item['id']).'/workbook/worksheets/Sheet1', [], ['name' => (string) $config['worksheet_name']]);
        }

        return $this->shapeItem($item);
    }
}
