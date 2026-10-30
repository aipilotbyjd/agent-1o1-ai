<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;

class ExcelDownloadWorkbookNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_download_workbook';
    }

    public function name(): string
    {
        return 'Excel: Download Workbook';
    }

    public function description(): string
    {
        return 'Gets a short-lived link to download a workbook file.';
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
        $item = $this->graph($run, $config, 'GET', $this->item($config));

        return [...$this->shapeItem($item), 'download_url' => $item['@microsoft.graph.downloadUrl'] ?? null];
    }
}
