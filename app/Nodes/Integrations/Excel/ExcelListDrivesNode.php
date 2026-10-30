<?php

namespace App\Nodes\Integrations\Excel;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class ExcelListDrivesNode extends AbstractExcelNode
{
    public function type(): string
    {
        return 'excel_list_drives';
    }

    public function name(): string
    {
        return 'Excel: List Drives';
    }

    public function description(): string
    {
        return 'Lists your OneDrive and shared drives, or the document libraries of a SharePoint site.';
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
                'site_id' => Field::text('SharePoint site id', 'From Search SharePoint Sites. Empty lists your own drives.', 'contoso.sharepoint.com,1a2b,3c4d'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $site = trim((string) ($config['site_id'] ?? ''));

        if ($site !== '' && preg_match('/^[A-Za-z0-9.,:_-]{1,300}$/', $site) !== 1) {
            throw new InvalidArgumentException('The site id may only hold letters, digits, dots, commas, colons, _ and -.');
        }

        $drives = array_map(fn (array $drive): array => ['id' => $drive['id'] ?? null, 'name' => $drive['name'] ?? null, 'type' => $drive['driveType'] ?? null, 'url' => $drive['webUrl'] ?? null], array_values(array_filter((array) ($this->graph($run, $config, 'GET', $site !== '' ? '/sites/'.rawurlencode($site).'/drives' : '/me/drives')['value'] ?? []), 'is_array')));

        return ['drives' => $drives, 'count' => count($drives)];
    }
}
