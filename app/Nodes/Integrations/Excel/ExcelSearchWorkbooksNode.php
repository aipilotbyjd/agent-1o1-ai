<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class ExcelSearchWorkbooksNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_search_workbooks';
    }

    public function name(): string
    {
        return 'Excel: Search Workbooks';
    }

    public function description(): string
    {
        return 'Finds workbooks in OneDrive or SharePoint by name or content.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                ...$this->credentialFields(),
                'drive_id' => $this->driveField(),
                'query' => Field::text('Search for', null, 'budget'),
                'limit' => Field::advanced(Field::integer('Limit', 'The most to return.', 100, 1, 5000)),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $workbooks = $this->searchWorkbooks($run, $config, $this->requiredString($config, 'query', 'The search'), $this->limit($config));

        return ['workbooks' => $workbooks, 'count' => count($workbooks)];
    }
}
