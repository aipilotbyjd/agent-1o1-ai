<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class AhrefsListLostBacklinksNode extends AbstractAhrefsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'ahrefs_list_lost_backlinks';
    }

    public function name(): string
    {
        return 'Ahrefs: List Lost Backlinks';
    }

    public function description(): string
    {
        return 'The backlinks lost in the last 30 days, most recent first. As a trigger it fires when one is lost.';
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
            'select' => 'url_from,url_to,anchor,title,domain_rating_source,is_dofollow,is_lost,lost_reason,first_seen_link,last_seen',
            'target' => $this->requiredString($config, 'target', 'The target'),
            'mode' => $config['mode'] ?? null,
            'aggregation' => 'all',
            'history' => 'since:'.now()->utc()->subDays(30)->toDateString(),
            'where' => '{"field":"is_lost","is":["eq",true]}',
            'order_by' => 'last_seen:desc',
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
     * A link is known by the page it was on, the page it pointed to and when it went.
     */
    public function triggerItemId(array $item): string
    {
        return ($item['url_from'] ?? '').' > '.($item['url_to'] ?? '').' @ '.($item['last_seen'] ?? '');
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Ahrefs: Lost backlink';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when Ahrefs sees a link to the target has gone. Checked daily, as each check costs API units.';
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
