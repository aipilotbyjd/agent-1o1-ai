<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Contracts\SupportsTriggerMode;
use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Integrations\Concerns\ActsAsTrigger;
use App\Nodes\Support\Field;

class MetaAdsListNewLeadsNode extends AbstractMetaAdsNode implements SupportsTriggerMode
{
    use ActsAsTrigger;

    public function type(): string
    {
        return 'meta_ads_list_new_leads';
    }

    public function name(): string
    {
        return 'Meta Ads: List New Leads';
    }

    public function description(): string
    {
        return 'Reads the leads that arrived lately, from one form or every form of a Page. As a trigger it starts the flow for each one. Checks every few minutes.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Read;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['page_id'],
            'properties' => [
                ...$this->credentialFields(),
                'page_id' => Field::dynamic('Page', 'meta_ads.pages'),
                'form_id' => Field::dynamic('Only this form', 'meta_ads.lead_forms', 'Empty reads every form of the Page.', null, ['page_id']),
                'changed_within_minutes' => Field::advanced(Field::integer('Look back (minutes)', 'Only leads this recent.', 1440, 1, 43200)),
                'limit' => Field::integer('Max results', null, 50, 1, 500),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $limit = max(1, min(500, (int) ($config['limit'] ?? 50)));
        $since = now()->subMinutes((int) ($config['changed_within_minutes'] ?? 1440))->getTimestamp();
        $filtering = [['field' => 'time_created', 'operator' => 'GREATER_THAN', 'value' => $since]];
        $forms = filled($config['form_id'] ?? null) ? [$this->nodeId($config, 'form_id', 'The form')] : $this->pageFormIds($run, $config);
        $leads = [];

        foreach ($forms as $form) {
            $leads = [...$leads, ...array_map($this->shapeLead(...), $this->readAll($run, $config, "/{$form}/leads", ['fields' => self::LEAD_FIELDS, 'filtering' => $filtering], $limit))];
        }

        return ['leads' => $leads, 'count' => count($leads)];
    }

    /**
     * Every form of the Page.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function pageFormIds(Run $run, array $config): array
    {
        $page = $this->nodeId($config, 'page_id', 'The Page');

        return array_values(array_filter(array_map(fn (array $form): string => (string) ($form['id'] ?? ''), $this->readAll($run, $config, "/{$page}/leadgen_forms", ['fields' => 'id'], self::MAX_PAGE_SIZE, $this->pageToken($run, $config, $page)))));
    }

    public function triggerName(): string
    {
        return 'Meta Ads: New lead';
    }

    public function triggerDescription(): string
    {
        return 'Starts the flow when someone sends a lead form on Facebook or Instagram. Checks every few minutes.';
    }

    protected function triggerItemsPath(): ?string
    {
        return 'leads';
    }

    public function triggerReadConfig(array $config): array
    {
        return [...$config, 'changed_within_minutes' => 360, 'limit' => 500];
    }
}
