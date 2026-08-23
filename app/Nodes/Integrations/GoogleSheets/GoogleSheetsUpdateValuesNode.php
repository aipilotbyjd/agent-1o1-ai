<?php

namespace App\Nodes\Integrations\GoogleSheets;

use App\Models\Runs\Run;

class GoogleSheetsUpdateValuesNode extends AbstractGoogleSheetsNode
{
    public function type(): string
    {
        return 'google_sheets_update_values';
    }

    public function name(): string
    {
        return 'Google Sheets: Update Values';
    }

    public function description(): string
    {
        return 'Overwrites the values in a spreadsheet range.';
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['spreadsheet_id', 'range', 'values'],
            'properties' => [
                'access_token' => ['type' => 'string'],
                'credential_id' => ['type' => 'integer'],
                'spreadsheet_id' => ['type' => 'string'],
                'range' => ['type' => 'string'],
                'values' => ['type' => 'array'],
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $endpoint = "/spreadsheets/{$config['spreadsheet_id']}/values/{$config['range']}"
            .'?valueInputOption=USER_ENTERED';

        return $this->put($run, $endpoint, $config, [
            'values' => [$config['values']],
        ]);
    }
}
