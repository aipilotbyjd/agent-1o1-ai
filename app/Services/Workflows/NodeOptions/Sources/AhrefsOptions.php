<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;

/**
 * Ahrefs projects, keyword lists and Brand Radar reports, from its management API.
 * Ahrefs can't search them, so a search filters what came back.
 */
class AhrefsOptions extends HttpOptionsSource
{
    private const string BASE_URL = 'https://api.ahrefs.com/v3/management';

    public function sources(): array
    {
        return ['ahrefs.projects', 'ahrefs.keyword_lists', 'ahrefs.brand_radar_reports'];
    }

    protected function appName(): string
    {
        return 'Ahrefs';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return match ($source) {
            'ahrefs.keyword_lists' => $this->list($query, 'keyword-lists', 'keyword_lists', 'keyword_list_id', 'name'),
            'ahrefs.brand_radar_reports' => $this->list($query, 'brand-radar-reports', 'reports', 'report_id', 'report_name'),
            default => $this->list($query, 'projects', 'projects', 'project_id', 'project_name', 'url'),
        };
    }

    private function list(NodeOptionsQuery $query, string $path, string $key, string $id, string $name, ?string $detail = null): NodeOptionsPage
    {
        $items = $this->getJson($query, self::BASE_URL."/{$path}", ['output' => 'json'])[$key] ?? [];

        $options = collect(is_array($items) ? $items : [])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item[$id]) && $query->matches((string) ($item[$name] ?? '')))
            ->map(fn (array $item): NodeOption => new NodeOption((string) $item[$id], (string) ($item[$name] ?? $item[$id]), $detail !== null ? ($item[$detail] ?? null) : null));

        return new NodeOptionsPage($options);
    }
}
