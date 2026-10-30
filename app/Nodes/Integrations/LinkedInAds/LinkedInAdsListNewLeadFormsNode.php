<?php

namespace App\Nodes\Integrations\LinkedInAds;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class LinkedInAdsListNewLeadFormsNode extends AbstractLinkedInAdsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'linkedin_ads_list_new_lead_forms';
    }

    public function name(): string
    {
        return 'LinkedIn Ads: List New Lead Forms';
    }

    public function description(): string
    {
        return 'Lists the lead forms of an ad account or company page, newest first. As a trigger it starts the flow for each new one. Checks every few minutes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->formOwnerFields(),
                'limit' => Field::integer('Max results', null, 50, 1, 100),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $query = 'q=owner&owner='.$this->formOwner($config).'&count='.max(1, min(100, (int) ($config['limit'] ?? 50)));
        $forms = array_values(array_filter((array) ($this->linkedin($run, $config, 'GET', '/leadForms', $query)['elements'] ?? []), 'is_array'));

        usort($forms, fn (array $a, array $b): int => (int) ($b['created'] ?? 0) <=> (int) ($a['created'] ?? 0));

        return ['forms' => $forms, 'count' => count($forms)];
    }

    public function triggerName(): string
    {
        return 'LinkedIn Ads: New lead form';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when a lead form is created. Checks every few minutes.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'forms';
    }

    public function triggerListsNewestFirst(): bool
    {
        return true;
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'limit' => 100];
    }
}
