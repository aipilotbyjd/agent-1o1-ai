<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MetaAdsUpdateCreativeNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_update_creative';
    }

    public function name(): string
    {
        return 'Meta Ads: Update Creative';
    }

    public function description(): string
    {
        return 'Renames a creative or changes its status or labels. Its content cannot be changed once created.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['creative_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'creative_id' => Field::dynamic('Creative', 'meta_ads.creatives', null, null, ['account_id']),
                'name' => Field::text('Name'),
                'status' => Field::select('Status', ['ACTIVE', 'DELETED']),
                'adlabels' => Field::advanced(Field::json('Labels', 'A list of label objects.', 'array')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $id = $this->nodeId($config, 'creative_id', 'The creative');
        $params = $this->only($config, ['name', 'status', 'adlabels']);

        if ($params === []) {
            throw new InvalidArgumentException('Fill in at least one field to change.');
        }

        return ['id' => $id, 'updated' => (bool) ($this->graph($run, $config, 'POST', "/{$id}", $params)['success'] ?? true)];
    }
}
