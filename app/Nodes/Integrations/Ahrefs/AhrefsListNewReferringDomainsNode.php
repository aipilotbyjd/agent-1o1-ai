<?php

namespace App\Nodes\Integrations\Ahrefs;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class AhrefsListNewReferringDomainsNode extends AbstractAhrefsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'ahrefs_list_new_referring_domains';
    }

    public function name(): string
    {
        return 'Ahrefs: List Newest Referring Domains';
    }

    public function description(): string
    {
        return 'The domains first seen linking to the target most recently. As a trigger it fires when a new one is found.';
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
            'select' => 'domain,domain_rating,links_to_target,dofollow_links,traffic_domain,first_seen',
            'target' => $this->requiredString($config, 'target', 'The target'),
            'mode' => $config['mode'] ?? null,
            'history' => 'live',
            'order_by' => 'first_seen:desc',
            'limit' => $config['limit'] ?? 100,
        ]);

        return $this->shape($answer, 'refdomains');
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'refdomains';
    }

    /**
     * A referring domain is known by its name.
     */
    public function triggerItemId(array $item): string
    {
        return (string) ($item['domain'] ?? '');
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Ahrefs: New referring domain';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when Ahrefs finds a new domain linking to the target. Checked daily, as each check costs API units.';
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
