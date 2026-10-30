<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsArchiveLeadFormNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_archive_lead_form';
    }

    public function name(): string
    {
        return 'Meta Ads: Archive Lead Form';
    }

    public function description(): string
    {
        return 'Archives a lead form, so it can no longer be chosen for ads. Its leads stay.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['page_id', 'form_id'],
            'properties' => [
                ...$this->credentialFields(),
                'page_id' => Field::dynamic('Page', 'meta_ads.pages'),
                'form_id' => Field::dynamic('Lead form', 'meta_ads.lead_forms', null, null, ['page_id']),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->nodeId($config, 'form_id', 'The form');

        $this->graph($run, $config, 'POST', "/{$id}", ['status' => 'ARCHIVED'], $this->pageToken($run, $config, $this->nodeId($config, 'page_id', 'The Page')));

        return ['id' => $id, 'status' => 'ARCHIVED'];
    }
}
