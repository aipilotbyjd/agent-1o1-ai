<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Exceptions\ConnectorException;
use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Semrush projects, Position Tracking campaigns and finished audits. The API key goes
 * in the query, which is never logged, and each list costs Semrush's usual API units.
 */
class SemrushOptions extends HttpOptionsSource
{
    private const string BASE_URL = 'https://api.semrush.com';

    public function sources(): array
    {
        return ['semrush.projects', 'semrush.campaigns', 'semrush.snapshots'];
    }

    protected function appName(): string
    {
        return 'Semrush';
    }

    protected function http(NodeOptionsQuery $query): PendingRequest
    {
        return Http::acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        return match ($source) {
            'semrush.campaigns' => $this->campaigns($query),
            'semrush.snapshots' => $this->snapshots($query),
            default => $this->projects($query),
        };
    }

    private function projects(NodeOptionsQuery $query): NodeOptionsPage
    {
        $projects = $this->getJson($query, self::BASE_URL.'/management/v1/projects', ['key' => $query->token]);

        $options = collect($projects)
            ->filter(fn (mixed $project): bool => is_array($project) && isset($project['project_id']) && $query->matches((string) ($project['project_name'] ?? ''), (string) ($project['url'] ?? '')))
            ->map(fn (array $project): NodeOption => new NodeOption((string) $project['project_id'], (string) ($project['project_name'] ?? $project['project_id']), $project['url'] ?? null));

        return new NodeOptionsPage($options);
    }

    private function campaigns(NodeOptionsQuery $query): NodeOptionsPage
    {
        $answer = $this->getJson($query, self::BASE_URL.'/management/v1/projects/'.$this->project($query).'/tracking/campaigns', ['key' => $query->token]);
        $campaigns = array_is_list($answer) ? $answer : ($answer['campaigns'] ?? $answer['data'] ?? []);

        $options = collect(is_array($campaigns) ? $campaigns : [])
            ->filter(fn (mixed $campaign): bool => is_array($campaign) && isset($campaign['id']) && $query->matches((string) ($campaign['url'] ?? '')))
            ->map(fn (array $campaign): NodeOption => new NodeOption((string) $campaign['id'], (string) ($campaign['url'] ?? $campaign['id']), trim(($campaign['location']['name'] ?? '').' '.($campaign['device'] ?? '')) ?: null));

        return new NodeOptionsPage($options);
    }

    private function snapshots(NodeOptionsQuery $query): NodeOptionsPage
    {
        $snapshots = $this->getJson($query, self::BASE_URL.'/reports/v1/projects/'.$this->project($query).'/siteaudit/snapshots', ['key' => $query->token])['snapshots'] ?? [];

        $options = collect(is_array($snapshots) ? $snapshots : [])
            ->filter(fn (mixed $snapshot): bool => is_array($snapshot) && isset($snapshot['snapshot_id']))
            ->map(fn (array $snapshot): NodeOption => new NodeOption((string) $snapshot['snapshot_id'], is_numeric($snapshot['finish_date'] ?? null) ? gmdate('Y-m-d H:i', intdiv((int) $snapshot['finish_date'], 1000)).' UTC' : (string) $snapshot['snapshot_id']));

        return new NodeOptionsPage($options);
    }

    /**
     * @throws ConnectorException
     */
    private function project(NodeOptionsQuery $query): string
    {
        $project = trim((string) ($query->config['project_id'] ?? ''));

        return ctype_digit($project) ? $project : throw new ConnectorException('Choose a project first.');
    }
}
