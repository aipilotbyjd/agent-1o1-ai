<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;

class MetaAdsCreateLookalikeNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_create_lookalike';
    }

    public function name(): string
    {
        return 'Meta Ads: Create Lookalike Audience';
    }

    public function description(): string
    {
        return 'Creates an audience of people similar to a custom audience of at least 100 members.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'name', 'origin_audience_id', 'country'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'name' => Field::text('Name'),
                'origin_audience_id' => Field::dynamic('Source audience', 'meta_ads.audiences', null, null, ['account_id']),
                'country' => Field::text('Country', 'Two letters, like US.'),
                'ratio' => Field::text('Size', 'A share of the country from 0.01 to 0.20. Empty uses the most similar people.'),
                'starting_ratio' => Field::advanced(Field::text('Start at share', 'Smaller than the size.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $spec = isset($config['ratio']) && $config['ratio'] !== '' ? array_filter(['ratio' => (float) $config['ratio'], 'starting_ratio' => filled($config['starting_ratio'] ?? null) ? (float) $config['starting_ratio'] : null]) : ['type' => 'similarity'];

        return $this->graph($run, $config, 'POST', '/'.$this->accountPath($config).'/customaudiences', ['name' => $this->requiredString($config, 'name', 'The name'), 'subtype' => 'LOOKALIKE', 'origin_audience_id' => $this->nodeId($config, 'origin_audience_id', 'The source audience'), 'lookalike_spec' => [...$spec, 'country' => strtoupper($this->requiredString($config, 'country', 'The country'))]]);
    }
}
