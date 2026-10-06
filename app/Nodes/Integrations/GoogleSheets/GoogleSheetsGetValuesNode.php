<?php

namespace App\Nodes\Integrations\GoogleSheets;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class GoogleSheetsGetValuesNode extends AbstractGoogleSheetsNode
{
    public function type(): string
    {
        return 'google_sheets_get_values';
    }

    public function name(): string
    {
        return 'Google Sheets: Get Values';
    }

    public function description(): string
    {
        return 'Reads a range of cell values from a spreadsheet.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['spreadsheet_id', 'range'],
            'properties' => [
                ...$this->credentialFields(),
                'spreadsheet_id' => Field::dynamic('Spreadsheet', 'google_sheets.spreadsheets', 'Pick a spreadsheet, or enter its ID from the URL.', '1AbCdEfGhIjKlMnOp'),
                'range' => Field::dynamic('Sheet / range', 'google_sheets.sheets', 'Pick a sheet tab to use the whole sheet, or type an A1 range such as Sheet1!A1:D10.', 'Sheet1!A1:D10', dependsOn: ['spreadsheet_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get(
            $run,
            $this->valuesPath($config),
            $config,
        );
    }
}
