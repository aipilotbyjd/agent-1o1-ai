<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class AhrefsListNewBacklinksNode extends AbstractAhrefsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'ahrefs_list_new_backlinks';
    }

    public function name(): string
    {
        return 'Ahrefs: List Newest Backlinks';
    }

    public function description(): string
    {
        return 'The backlinks first seen most recently. As a trigger it fires when a new one is found.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['target'],
            'properties' => [
                ...$this->credentialFields(),
                'target' => Field::text('Target', 'A domain or a URL.', 'example.com'),
                'mode' => Field::advanced(Field::select('Scope', ['exact', 'prefix', 'domain', 'subdomains'], null, 'subdomains')),
                'limit' => Field::integer('Limit', 'The most rows to read each time. Ahrefs charges per row.', 100, 1, 1000),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $answer = $this->ahrefs($run, $config, 'GET', '/site-explorer/all-backlinks', [
            'select' => 'url_from,url_to,anchor,title,domain_rating_source,url_rating_source,traffic,is_dofollow,first_seen_link',
            'target' => $this->requiredString($config, 'target', 'The target'),
            'mode' => $config['mode'] ?? null,
            'aggregation' => 'all',
            'history' => 'live',
            'order_by' => 'first_seen_link:desc',
            'limit' => $config['limit'] ?? 100,
        ]);

        return $this->shape($answer, 'backlinks');
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'backlinks';
    }

    /**
     * A link is known by the page it is on and the page it points to.
     */
    public function triggerItemId(array $item): string
    {
        return ($item['url_from'] ?? '').' > '.($item['url_to'] ?? '');
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Ahrefs: New backlink';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when Ahrefs finds a new link to the target. Checked daily, as each check costs API units.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 1440;
    }

    /**
     * The list holds the newest first.
     */
    public function triggerListsNewestFirst(): bool
    {
        return true;
    }
}
