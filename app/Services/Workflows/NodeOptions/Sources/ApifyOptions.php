<?php

namespace App\Services\Workflows\NodeOptions\Sources;

use App\Services\Workflows\NodeOptions\NodeOption;
use App\Services\Workflows\NodeOptions\NodeOptionsPage;
use App\Services\Workflows\NodeOptions\NodeOptionsQuery;

/**
 * The account's Actors, tasks, datasets, key-value stores, schedules and webhooks.
 * Apify takes the token as a bearer token and can't search these lists, so a search
 * filters the newest page. A run's own storage has no name, so only named ones show.
 */
class ApifyOptions extends HttpOptionsSource
{
    private const string API = 'https://api.apify.com/v2';

    /**
     * Each source's address, and what its query adds.
     *
     * @var array<string, array{0: string, 1: array<string, mixed>}>
     */
    private const array LISTS = [
        'apify.actors' => ['/actors', ['my' => 'true']],
        'apify.tasks' => ['/actor-tasks', []],
        'apify.datasets' => ['/datasets', []],
        'apify.key_value_stores' => ['/key-value-stores', []],
        'apify.schedules' => ['/schedules', []],
        'apify.webhooks' => ['/webhooks', []],
    ];

    public function sources(): array
    {
        return array_keys(self::LISTS);
    }

    protected function appName(): string
    {
        return 'Apify';
    }

    public function load(string $source, NodeOptionsQuery $query): NodeOptionsPage
    {
        [$path, $params] = self::LISTS[$source];
        $items = collect($this->getJson($query, self::API.$path, [...$params, 'limit' => 200, 'desc' => 'true'])['data']['items'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && isset($item['id']))
            ->map(fn (array $item): array => [...$item, 'label' => $this->label($item)])
            ->filter(fn (array $item): bool => $query->matches((string) $item['label']));

        return new NodeOptionsPage($items->map(fn (array $item): NodeOption => new NodeOption((string) $item['id'], (string) $item['label'], $item['title'] ?? null)));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function label(array $item): string
    {
        $name = $item['name'] ?? $item['requestUrl'] ?? $item['title'] ?? $item['id'];

        return isset($item['username']) ? "{$item['username']}/{$name}" : (string) $name;
    }
}
