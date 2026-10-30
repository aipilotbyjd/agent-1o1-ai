<?php

namespace App\Nodes\Integrations\MetaAds;

use App\Enums\Agents\ActionEffect;
use App\Models\Runs\Run;
use App\Nodes\Support\Field;
use InvalidArgumentException;

class MetaAdsCreateCreativeNode extends AbstractMetaAdsNode
{
    public function type(): string
    {
        return 'meta_ads_create_creative';
    }

    public function name(): string
    {
        return 'Meta Ads: Create Creative';
    }

    public function description(): string
    {
        return 'Creates an ad creative, the picture, video and words of an ad, from a spec or an existing Page post. It spends nothing on its own.';
    }

    public function effect(array $config): ActionEffect
    {
        return ActionEffect::Write;
    }

    public function configSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['account_id'],
            'properties' => [
                ...$this->credentialFields(),
                ...$this->accountField(),
                'name' => Field::text('Name'),
                'object_story_spec' => Field::json('Story spec', 'The Page and its link_data, photo_data or video_data. Or give an existing post below.'),
                'object_story_id' => Field::text('Or an existing post', 'As page id_post id.'),
                'url_tags' => Field::advanced(Field::text('URL tags', 'Added to the addresses clicked.')),
                'authorization_category' => Field::advanced(Field::select('Political ad', ['POLITICAL' => 'Political', 'POLITICAL_WITH_DIGITALLY_CREATED_MEDIA' => 'Political, with digitally created media'])),
                'asset_feed_spec' => Field::advanced(Field::json('Asset feed', 'For dynamic creative.')),
                'degrees_of_freedom_spec' => Field::advanced(Field::json('Allowed changes', 'What Meta may adapt.')),
                'more_fields' => Field::advanced(Field::json('More fields', 'Any other field of the Marketing API\'s creative.')),
            ],
        ];
    }

    public function execute(Run $run, array $config, array $context): array
    {
        $spec = $this->jsonSetting($config, 'object_story_spec', 'The story spec');

        if ($spec === [] && blank($config['object_story_id'] ?? null) && blank($config['asset_feed_spec'] ?? null) && blank($config['more_fields'] ?? null)) {
            throw new InvalidArgumentException('Give a story spec or an existing post.');
        }

        return $this->graph($run, $config, 'POST', '/'.$this->accountPath($config).'/adcreatives', [...$this->jsonSetting($config, 'more_fields', 'More fields'), 'object_story_spec' => $spec ?: null, 'asset_feed_spec' => $this->jsonSetting($config, 'asset_feed_spec', 'The asset feed') ?: null, 'degrees_of_freedom_spec' => $this->jsonSetting($config, 'degrees_of_freedom_spec', 'The allowed changes') ?: null, ...$this->only($config, ['name', 'object_story_id', 'url_tags', 'authorization_category'])]);
    }
}
