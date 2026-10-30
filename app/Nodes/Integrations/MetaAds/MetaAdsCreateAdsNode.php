<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MetaAdsCreateAdsNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_create_ads';
    }

    public function name(): string
    {
        return 'Meta Ads: Create Ads in Bulk';
    }

    public function description(): string
    {
        return 'Creates up to 50 ads in one call. Each is paused unless it says otherwise, and each reports its own result.';
    }

    public function effect(array $config): ActionEffect
    {
        return $this->bulkEffect($config);
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id', 'ads'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'ads' => Field::json('Ads', 'A list of up to 50, each with name, adset_id, creative_id or creative, and optionally status.', 'array'),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $ads = $this->jsonSetting($config, 'ads', 'The ads', true);

        if (count($ads) > 50) {
            throw new InvalidArgumentException('Meta takes at most 50 ads in one batch.');
        }

        $account = $this->accountPath($config);
        $batch = array_map(fn (mixed $ad): array => ['method' => 'POST', 'relative_url' => "{$account}/ads", 'body' => http_build_query(self::encodeParams($this->adParams((array) $ad)))], array_values($ads));
        $answers = $this->graph($run, $config, 'POST', '/', ['batch' => $batch]);

        return ['results' => array_map(fn (mixed $answer): array => ['ok' => (int) data_get($answer, 'code') === 200, 'body' => json_decode((string) data_get($answer, 'body'), true)], array_values($answers))];
    }

    /**
     * Live if any of the ads asks to be.
     *
     * @param  array<string, mixed>  $config
     */
    private function bulkEffect(array $config): ActionEffect
    {
        foreach ($this->jsonSetting($config, 'ads', 'The ads') as $ad) {
            if ($this->statusEffect((array) $ad) !== ActionEffect::Write) {
                return ActionEffect::External;
            }
        }

        return ActionEffect::Write;
    }
}
