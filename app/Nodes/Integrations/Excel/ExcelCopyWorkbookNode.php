<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelCopyWorkbookNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_copy_workbook';
    }

    public function name(): string
    {
        return 'Excel: Copy Workbook';
    }

    public function description(): string
    {
        return 'Copies a workbook to a new file, in another folder or drive if asked. Graph copies in the background: the copy appears shortly after.';
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
                'name' => Field::text('New name', 'Empty keeps the name, which needs another folder.', 'Q4 Budget (final).xlsx'),
                'folder_id' => Field::dynamic('Destination folder', 'excel.folders', 'Pick a folder, or enter its id. Empty is the same folder.', null, uses: ['drive_id']),
                'destination_drive_id' => Field::advanced(Field::text('Destination drive', 'The drive to copy to, when not the same one. Needs a destination folder.', 'b!abc')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $parent = array_filter(['driveId' => filled($config['destination_drive_id'] ?? null) ? rawurldecode($this->pathId($config, 'destination_drive_id', 'The destination drive')) : null, 'id' => filled($config['folder_id'] ?? null) ? rawurldecode($this->pathId($config, 'folder_id', 'The folder')) : null]);
        $body = array_filter(['name' => filled($config['name'] ?? null) ? (string) $config['name'] : null, 'parentReference' => $parent !== [] ? $parent : null]);
        $response = $this->graphResponse($run, $config, 'POST', $this->item($config).'/copy', [], $body);

        $this->assertOk($response);

        return ['accepted' => true, 'status' => $response->status(), 'monitor_url' => $response->header('Location') ?: null];
    }
}
