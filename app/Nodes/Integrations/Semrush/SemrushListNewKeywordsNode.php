<?php

namespace App\Nodes\Integrations\Semrush;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class SemrushListNewKeywordsNode extends AbstractSemrushReportNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'semrush_list_new_keywords';
    }

    public function name(): string
    {
        return 'Semrush: List New Organic Keywords';
    }

    public function description(): string
    {
        return 'Keywords that have entered Google\'s top 100 for a domain. As a trigger it fires when one enters.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['domain', 'database'],
            'properties' => [
                ...$this->credentialFields(),
                'domain' => Field::text('Domain', null, 'example.com'),
                'database' => $this->databaseField(),
                'limit' => $this->limitField(100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        return $this->seoReport($run, $config, 'domain_organic', 'Ph,Po,Pp,Nq,Cp,Ur,Tr,Td', ['domain' => $this->domain($config), 'display_positions' => 'new']);
    }

    /**
     * Where the item list sits in execute()'s output.
     */
    protected function triggerItemsPath(): ?string
    {
        return 'rows';
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
        return 'Semrush: New organic keyword';
    }

    /**
     * The description shown in Triggers & Events.
     */
    public function triggerDescription(): string
    {
        return 'Starts the flow when a keyword enters Google\'s top 100 for the domain. Checked daily, as each check costs API units.';
    }

    /**
     * The default minutes between polls when used as a trigger.
     */
    public function triggerPollMinutes(): int
    {
        return 1440;
    }

    /**
     * The config a poll reads with.
     */
    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => (int) ($config['limit'] ?? 100)];
    }
}
