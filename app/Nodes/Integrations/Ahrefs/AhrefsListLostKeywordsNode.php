<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class AhrefsListLostKeywordsNode extends AbstractAhrefsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'ahrefs_list_lost_keywords';
    }

    public function name(): string
    {
        return 'Ahrefs: List Lost Organic Keywords';
    }

    public function description(): string
    {
        return 'The keywords the target stopped ranking for, compared with some days ago. As a trigger it fires when there is a change.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['target', 'country'],
            'properties' => [
                ...$this->credentialFields(),
                'target' => Field::text('Target', 'A domain or a URL.', 'example.com'),
                'country' => Field::text('Country', 'A two-letter code, lower case.', 'us'),
                'days' => Field::integer('Compare with', 'Days back.', 7, 1, 90),
                'mode' => Field::advanced(Field::select('Scope', ['exact', 'prefix', 'domain', 'subdomains'], null, 'subdomains')),
                'limit' => Field::integer('Limit', 'The most rows to read each time. Ahrefs charges per row.', 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $days = max(1, (int) ($config['days'] ?? 7));
        $answer = $this->ahrefs($run, $config, 'GET', '/site-explorer/organic-keywords', [
            'select' => 'keyword_prev,best_position_prev,best_position_url_prev,volume_prev,sum_traffic_prev,status',
            'target' => $this->requiredString($config, 'target', 'The target'),
            'country' => $this->requiredString($config, 'country', 'The country'),
            'mode' => $config['mode'] ?? null,
            'date' => now()->utc()->toDateString(),
            'date_compared' => now()->utc()->subDays($days)->toDateString(),
            'where' => '{"field":"status","is":["eq","right"]}',
            'limit' => $config['limit'] ?? 100,
        ]);
        $keywords = array_map(fn (array $keyword): array => ['keyword' => $keyword['keyword_prev'] ?? null, ...$keyword], array_values(array_filter((array) ($answer['keywords'] ?? []), 'is_array')));

        return ['keywords' => $keywords, 'count' => count($keywords)];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'keywords';
    }

    /**
     * A keyword is known by its text.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['keyword'] ?? '');
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Ahrefs: Lost organic keyword';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when the target stops ranking for a keyword. Checked daily, as each check costs API units.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 1440;
    }
}
