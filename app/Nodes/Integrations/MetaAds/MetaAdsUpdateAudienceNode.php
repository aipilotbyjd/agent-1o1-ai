<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MetaAdsUpdateAudienceNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_update_audience';
    }

    public function name(): string
    {
        return 'Meta Ads: Update Custom Audience';
    }

    public function description(): string
    {
        return 'Renames a custom audience or changes its description.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['audience_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'audience_id' => Field::dynamic('Audience', 'meta_ads.audiences', null, null, ['account_id']),
                'name' => Field::text('Name'),
                'description' => Field::text('Description'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->nodeId($config, 'audience_id', 'The audience');
        $params = $this->only($config, ['name', 'description']);

        if ($params === []) {
            throw new InvalidArgumentException('Fill in at least one field to change.');
        }

        return ['id' => $id, 'updated' => (bool) ($this->graph($run, $config, 'POST', "/{$id}", $params)['success'] ?? true)];
    }
}
