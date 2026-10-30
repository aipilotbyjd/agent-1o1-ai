<?php

namespace App\Nodes\Integrations\Semrush;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class SemrushListNewReferringDomainsNode extends AbstractSemrushReportNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'semrush_list_new_referring_domains';
    }

    public function name(): string
    {
        return 'Semrush: List Newest Referring Domains';
    }

    public function description(): string
    {
        return 'The domains first seen linking to a target most recently. As a trigger it fires when a new one is found. Semrush charges 40 API units per row.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['target', 'target_type'],
            'properties' => [
                ...$this->credentialFields(),
                'target' => Field::text('Target', 'A root domain, a subdomain or a URL.', 'example.com'),
                'target_type' => $this->targetTypeField(),
                'limit' => $this->limitField(100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'backlinks_refdomains', 'domain,domain_ascore,backlinks_num,ip,country,first_seen,last_seen', ['target' => $this->requiredString($config, 'target', 'The target'), 'display_sort' => 'first_seen_desc'], '/analytics/v1/');
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'rows';
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
        return 'Semrush: New referring domain';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when Semrush finds a new domain linking to the target. Checked daily, as each check costs API units.';
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

    /**
     * The config a poll reads with.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => (int) ($config['limit'] ?? 100)];
    }
}
