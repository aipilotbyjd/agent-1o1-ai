<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;

/**
 * Workbooks, sheets, tables, named items, charts, folders and drives, via Microsoft
 * Graph. A dependent list needs a workbook, read from the node's settings and checked
 * before it goes in a URL. Graph's next links are never followed: they are full URLs
 * the client could swap for any other.
 */
class ExcelOptions extends HttpOptionsSource
{
    private const string BASE_URL = 'https://graph.microsoft.com/v1.0';

    private const int PAGE_SIZE = 50;

    public function sources(): array
    {
        return ['excel.workbooks', 'excel.worksheets', 'excel.tables', 'excel.names', 'excel.charts', 'excel.folders', 'excel.drives'];
    }

    protected function appName(): string
    {
        return 'Excel';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return match ($source) {
            'excel.worksheets' => $this->named($query, '/worksheets'),
            'excel.tables' => $this->named($query, '/tables'),
            'excel.names' => $this->named($query, '/names'),
            'excel.charts' => $this->named($query, '/worksheets/'.rawurlencode($this->required($query, 'worksheet')).'/charts'),
            'excel.folders' => $this->folders($query),
            'excel.drives' => $this->drives($query),
            default => $this->workbooks($query),
        };
    }

    /**
     * Graph has no listing of files by kind, so workbooks are found by a search for
     * what's typed or for the extension.
     */
    private function workbooks(NodeOptionsQuery $query): NodeOptionsPage
    {
        $term = str_replace("'", "''", $query->search ?? '.xlsx');
        $found = $this->getJson($query, self::BASE_URL.$this->drive($query)."/root/search(q='".rawurlencode($term)."')", ['$top' => self::PAGE_SIZE, '$select' => 'id,name,webUrl,lastModifiedDateTime'])['value'] ?? [];

        $options = collect(is_array($found) ? $found : [])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']) && preg_match('/\.(xlsx|xlsm|xlsb)$/i', (string) ($item['name'] ?? '')) === 1)
            ->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], (string) $item['name'], isset($item['lastModifiedDateTime']) ? "Changed {$item['lastModifiedDateTime']}" : null));

        return new NodeOptionsPage($options);
    }

    /**
     * The items of a workbook, by name, which is what the nodes take.
     */
    private function named(NodeOptionsQuery $query, string $path): NodeOptionsPage
    {
        $items = $this->getJson($query, self::BASE_URL.$this->drive($query).'/items/'.$this->workbook($query).'/workbook'.$path)['value'] ?? [];

        $options = collect(is_array($items) ? $items : [])
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['name'] ?? null) && $query->matches((string) $item['name']))
            ->map(fn (array $item): NodeOption => new NodeOption((string) $item['name'], (string) $item['name']));

        return new NodeOptionsPage($options);
    }

    private function folders(NodeOptionsQuery $query): NodeOptionsPage
    {
        $items = $this->getJson($query, self::BASE_URL.$this->drive($query).'/root/children', ['$top' => 200, '$select' => 'id,name,folder'])['value'] ?? [];

        $options = collect(is_array($items) ? $items : [])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id'], $item['folder']) && $query->matches((string) ($item['name'] ?? '')))
            ->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], (string) ($item['name'] ?? $item['id'])));

        return new NodeOptionsPage($options);
    }

    private function drives(NodeOptionsQuery $query): NodeOptionsPage
    {
        $items = $this->getJson($query, self::BASE_URL.'/me/drives', ['$select' => 'id,name,driveType'])['value'] ?? [];

        $options = collect(is_array($items) ? $items : [])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']) && $query->matches((string) ($item['name'] ?? '')))
            ->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], (string) ($item['name'] ?? $item['id']), $item['driveType'] ?? null));

        return new NodeOptionsPage($options);
    }

    /**
     * @throws ConnectorException
     */
    private function drive(NodeOptionsQuery $query): string
    {
        $drive = trim((string) ($query->config['drive_id'] ?? ''));

        return $drive === '' ? '/me/drive' : '/drives/'.$this->checkedId($drive);
    }

    /**
     * @throws ConnectorException
     */
    private function workbook(NodeOptionsQuery $query): string
    {
        return $this->checkedId($this->required($query, 'workbook_id'));
    }

    /**
     * @throws ConnectorException
     */
    private function required(NodeOptionsQuery $query, string $key): string
    {
        $value = trim((string) ($query->config[$key] ?? ''));

        return $value !== '' && preg_match('/[\x00-\x1F\/\\\\?#]/u', $value) !== 1 ? $value : throw new ConnectorException('Choose the fields above first.');
    }

    /**
     * @throws ConnectorException
     */
    private function checkedId(string $id): string
    {
        return preg_match('/^[A-Za-z0-9!_.\-]{1,256}$/', $id) === 1 ? rawurlencode($id) : throw new ConnectorException('Choose the fields above first.');
    }
}
