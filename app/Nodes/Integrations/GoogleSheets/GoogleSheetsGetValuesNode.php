<?php

namespace App\Nodes\Integrations\GoogleSheets;

use App\Models\Runs\Run;

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

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['spreadsheet_id', 'range'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'spreadsheet_id' => ['type' => 'string'],
                'range' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->get(
            $run,
            "/spreadsheets/{$config['spreadsheet_id']}/values/{$config['range']}",
            $config,
        );
    }
}
