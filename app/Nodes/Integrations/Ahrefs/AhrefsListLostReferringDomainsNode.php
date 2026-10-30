<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class AhrefsListLostReferringDomainsNode extends AbstractAhrefsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'ahrefs_list_lost_referring_domains';
    }

    public function name(): string
    {
        return 'Ahrefs: List Lost Referring Domains';
    }

    public function description(): string
    {
        return 'The domains that stopped linking to the target in the last 30 days, most recent first. As a trigger it fires when one is lost.';
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
        $answer = $this->ahrefs($run, $config, 'GET', '/site-explorer/refdomains', [
            'select' => 'domain,domain_rating,links_to_target,traffic_domain,first_seen,last_seen',
            'target' => $this->requiredString($config, 'target', 'The target'),
            'mode' => $config['mode'] ?? null,
            'history' => 'since:'.now()->utc()->subDays(30)->toDateString(),
            'order_by' => 'last_seen:desc',
            'limit' => $config['limit'] ?? 100,
        ]);
        $lost = array_values(array_filter((array) ($answer['refdomains'] ?? []), fn (mixed $domain): bool => is_array($domain) && filled($domain['last_seen'] ?? null)));

        return ['refdomains' => $lost, 'count' => count($lost)];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'refdomains';
    }

    /**
     * A lost domain is known by its name and when it went.
     */
    public function triggerItemId(array $item): string
    {
        return ($item['domain'] ?? '').' @ '.($item['last_seen'] ?? '');
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Ahrefs: Lost referring domain';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a domain stops linking to the target. Checked daily, as each check costs API units.';
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
