<?php

namespace App\Nodes\Integrations\Semrush;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class SemrushListLostBacklinksNode extends AbstractSemrushReportNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'semrush_list_lost_backlinks';
    }

    public function name(): string
    {
        return 'Semrush: List Lost Backlinks';
    }

    public function description(): string
    {
        return 'The backlinks Semrush has marked as lost, last seen first. As a trigger it fires when one is lost. Semrush charges 40 API units per row.';
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
        $report = $this->seoReport($run, $config, 'backlinks', 'source_url,source_title,target_url,anchor,page_ascore,nofollow,first_seen,last_seen,newlink,lostlink', ['target' => $this->requiredString($config, 'target', 'The target'), 'display_sort' => 'last_seen_desc'], '/analytics/v1/');
        $lost = array_values(array_filter($report['rows'], fn (array $row): bool => in_array(strtolower((string) ($row['lostlink'] ?? '')), ['true', '1'], true)));

        return ['rows' => $lost, 'count' => count($lost)];
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'rows';
    }

    /**
     * A link is known by the page it was on and the page it pointed to.
     */
    public function triggerItemId(array $item): string
    {
        return ($item['source_url'] ?? '').' > '.($item['target_url'] ?? '');
    }

    /**
     * The name shown in Triggers & Events.
     */
    public function triggerName(): string
    {
        return 'Semrush: Lost backlink';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when Semrush marks a link to the target as lost. Checked daily, as each check costs API units.';
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
