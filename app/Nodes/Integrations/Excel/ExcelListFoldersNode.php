<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelListFoldersNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_list_folders';
    }

    public function name(): string
    {
        return 'Excel: List Folders';
    }

    public function description(): string
    {
        return 'Lists the folders in a folder of a drive.';
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
                'folder_id' => Field::dynamic('Folder', 'excel.folders', 'Empty is the top of the drive.', null, uses: ['drive_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $path = filled($config['folder_id'] ?? null) ? $this->item($config, 'folder_id', 'The folder') : $this->drive($config).'/root';
        $folders = array_values(array_filter((array) ($this->graph($run, $config, 'GET', $path.'/children', ['$top' => 200, '$select' => 'id,name,webUrl,folder,parentReference'])['value'] ?? []), fn (mixed $item): bool => is_array($item) && isset($item['folder'])));

        return ['folders' => array_map($this->shapeItem(...), $folders), 'count' => count($folders)];
    }
}
