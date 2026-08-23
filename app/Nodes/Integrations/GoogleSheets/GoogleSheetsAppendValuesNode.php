<?php

namespace App\Nodes\Integrations\GoogleSheets;

use App\Models\Runs\Run;

class GoogleSheetsAppendValuesNode extends AbstractGoogleSheetsNode
{
    public function type(): string
    {
        return 'google_sheets_append_values';
    }

    public function name(): string
    {
        return 'Google Sheets: Append Values';
    }

    public function description(): string
    {
        return 'Appends a row of values to a spreadsheet range.';
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
        $endpoint = "/spreadsheets/{$config['spreadsheet_id']}/values/{$config['range']}:append"
            .'?valueInputOption=USER_ENTERED';

        return $this->post($run, $endpoint, $config, [
            'values' => [$config['values']],
        ]);
    }
}
